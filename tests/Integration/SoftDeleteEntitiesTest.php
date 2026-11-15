<?php

namespace CodeSleeve\Holloway\Tests\Integration;

use ReflectionProperty;
use Illuminate\Events\Dispatcher;
use Illuminate\Database\Capsule\Manager as Capsule;
use CodeSleeve\Holloway\{Holloway, Mapper};
use CodeSleeve\Holloway\Tests\Fixtures\Entities\{Pack, Pup};
use CodeSleeve\Holloway\Tests\Helpers\CanBuildTestFixtures;

class SoftDeleteEntitiesTest extends TestCase
{
    use CanBuildTestFixtures;

    protected $originalEventManager;
    protected $eventManagerProperty;

    public static function setUpBeforeClass(): void
    {
        Holloway::instance()->register([
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\CollarMapper',
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\CompanyMapper',
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\PackMapper',
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\PupFoodMapper',
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\PupMapper',
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\UserMapper',
        ]);
    }

    public function setUp() : void
    {
        parent::setUp();

        // Listeners are registered on a static dispatcher, so give every test a fresh one and restore it afterwards.
        $this->eventManagerProperty = new ReflectionProperty(Mapper::class, 'eventManager');
        $this->eventManagerProperty->setAccessible(true);
        $this->originalEventManager = $this->eventManagerProperty->getValue();

        Mapper::setEventManager(new Dispatcher);
    }

    public function tearDown() : void
    {
        $this->eventManagerProperty->setValue(null, $this->originalEventManager);   // May be null, which setEventManager() does not accept.

        parent::tearDown();
        Holloway::instance()->flushEntityCache();
    }

    /** @test */
    public function it_soft_deletes_a_pup_and_restores_it_firing_the_events_for_each()
    {
        // given
        $this->buildFixtures();
        $events = [];
        $mapper = $this->pups();
        foreach (['removing', 'removed', 'restoring', 'restored'] as $event) {
            $mapper->registerPersistenceEvent($event, function($pup) use (&$events, $event) {
                $events[] = $event;
            });
        }

        // when
        $mapper->remove($mapper->find(1));
        $deleted = $mapper->withTrashed()->find(1);
        $restored = $mapper->restore($deleted);

        // then
        $this->assertTrue($restored);
        $this->assertSame(['removing', 'removed', 'restoring', 'restored'], $events);
        $this->assertNotNull($mapper->find(1));
        $this->assertNull(Capsule::table('pups')->where('id', 1)->value('deleted_at'));
    }

    /** @test */
    public function a_removing_listener_that_returns_false_cancels_the_removal()
    {
        // given
        $this->buildFixtures();
        $mapper = $this->pups();
        $mapper->registerPersistenceEvent('removing', fn($pup) => false);

        // when
        $removed = $mapper->remove($mapper->find(1));

        // then
        $this->assertFalse($removed);
        $this->assertNull(Capsule::table('pups')->where('id', 1)->value('deleted_at'));
    }

    /** @test */
    public function a_restoring_listener_that_returns_false_cancels_the_restore()
    {
        // given
        $this->buildFixtures();
        $mapper = $this->pups();
        $mapper->remove($mapper->find(1));
        $mapper->registerPersistenceEvent('restoring', fn($pup) => false);

        // when
        $restored = $mapper->restore($mapper->withTrashed()->find(1));

        // then
        $this->assertFalse($restored);
        $this->assertNotNull(Capsule::table('pups')->where('id', 1)->value('deleted_at'));
    }

    /** @test */
    public function it_soft_deletes_and_restores_many_entities_at_once()
    {
        // given
        $this->buildFixtures();
        $mapper = $this->pups();
        $pups = $mapper->where('pack_id', 2)->get();

        // when
        $removed = $mapper->remove($pups);

        // then
        $this->assertTrue($removed);
        $this->assertCount(0, $mapper->where('pack_id', 2)->get());
        $this->assertCount(2, $mapper->onlyTrashed()->where('pack_id', 2)->get());

        // when
        $restored = $mapper->restore($mapper->onlyTrashed()->where('pack_id', 2)->get());

        // then
        $this->assertTrue($restored);
        $this->assertCount(2, $mapper->where('pack_id', 2)->get());
    }

    /** @test */
    public function it_really_deletes_an_entity_when_it_is_force_removed()
    {
        // given
        $this->buildFixtures();
        $mapper = $this->pups();
        Capsule::table('collars')->where('pup_id', 5)->delete();
        $lucky = $mapper->find(5);

        // when
        $removed = $mapper->forceRemove($lucky);

        // then
        $this->assertTrue($removed);
        $this->assertFalse(Capsule::table('pups')->where('id', 5)->exists());

        // when the next removal goes back to being a soft delete
        $mapper->remove($mapper->find(6));

        // then
        $this->assertNotNull(Capsule::table('pups')->where('id', 6)->value('deleted_at'));
    }

    /** @test */
    public function it_deletes_the_row_of_a_mapper_that_doesnt_soft_delete_and_forgets_the_entity()
    {
        // given
        $this->buildFixtures();
        Capsule::table('packs')->insert(['id' => 3, 'name' => 'Empty Pack']);
        $mapper = Holloway::instance()->getMapper(Pack::class);
        $empty = $mapper->find(3);

        // when
        $removed = $mapper->remove($empty);

        // then
        $this->assertTrue($removed);
        $this->assertFalse(Capsule::table('packs')->where('id', 3)->exists());
        $this->assertNull($mapper->find(3));
    }

    /** @test */
    public function it_hard_deletes_the_rows_of_a_bulk_delete_through_a_query_without_firing_removal_events()
    {
        // given
        $this->buildFixtures();
        Capsule::table('packs')->insert([['id' => 3, 'name' => 'Temp A'], ['id' => 4, 'name' => 'Temp B']]);
        $events = [];
        $mapper = Holloway::instance()->getMapper(Pack::class);
        foreach (['removing', 'removed'] as $event) {
            $mapper->registerPersistenceEvent($event, function($pack) use (&$events, $event) {
                $events[] = $event;
            });
        }

        // when
        $deleted = $mapper->where('name', 'like', 'Temp %')->delete();

        // then
        $this->assertSame(2, $deleted);
        $this->assertSame([], $events);
        $this->assertFalse(Capsule::table('packs')->whereIn('id', [3, 4])->exists());
    }

    /** @test */
    public function it_accepts_any_callable_as_a_persistence_listener()
    {
        // given
        $this->buildFixtures();
        $this->heard = [];
        $mapper = $this->pups();
        $mapper->registerPersistenceEvent('removing', [$this, 'hear']);

        // when
        $mapper->remove($mapper->find(1));

        // then
        $this->assertSame(['Tobias'], $this->heard);
    }

    public $heard = [];

    /**
     * A listener for the tests, which is a callable that isn't a closure.
     */
    public function hear($pup)
    {
        $this->heard[] = $pup->first_name;
    }

    /**
     * @return Mapper
     */
    private function pups() : Mapper
    {
        return Holloway::instance()->getMapper(Pup::class);
    }
}
