<?php

namespace CodeSleeve\Holloway\Tests\Integration;

use ReflectionProperty;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Database\Capsule\Manager as Capsule;
use CodeSleeve\Holloway\{Holloway, Mapper, HollowayServiceProvider};
use CodeSleeve\Holloway\Tests\Fixtures\Entities\{Pack, Pup};
use CodeSleeve\Holloway\Tests\Helpers\CanBuildTestFixtures;

class PersistenceEventTest extends TestCase
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
    }

    /** @test */
    public function a_creating_listener_that_returns_false_cancels_the_insert()
    {
        // given
        $this->buildFixtures();
        $pupMapper = Holloway::instance()->getMapper(Pup::class);
        $pupMapper->registerPersistenceEvent('creating', fn($pup) => false);

        // when
        $stored = $pupMapper->store(new Pup(Holloway::instance()->getMapper(Pack::class)->find(2), 'Snowball', 'Adams', 'white'));

        // then
        $this->assertFalse($stored);
        $this->assertNull($pupMapper->where('first_name', 'Snowball')->first());
    }

    /** @test */
    public function a_storing_listener_that_returns_false_cancels_the_store()
    {
        // given
        $this->buildFixtures();
        $pupMapper = Holloway::instance()->getMapper(Pup::class);
        $pupMapper->registerPersistenceEvent('storing', fn($pup) => false);

        // when
        $stored = $pupMapper->store(new Pup(Holloway::instance()->getMapper(Pack::class)->find(2), 'Snowball', 'Adams', 'white'));

        // then
        $this->assertFalse($stored);
        $this->assertNull($pupMapper->where('first_name', 'Snowball')->first());
    }

    /** @test */
    public function an_updating_listener_that_returns_false_cancels_the_update()
    {
        // given
        $this->buildFixtures();
        $pupMapper = Holloway::instance()->getMapper(Pup::class);
        $pupMapper->registerPersistenceEvent('updating', fn($pup) => false);
        $tobi = $pupMapper->find(1);
        $tobi->setFirstName('Toby');

        // when
        $stored = $pupMapper->store($tobi);

        // then
        $this->assertFalse($stored);
        $this->assertEquals('Tobias', Capsule::table('pups')->where('id', 1)->value('first_name'));
    }

    /** @test */
    public function a_removing_listener_that_returns_false_cancels_the_remove()
    {
        // given
        $this->buildFixtures();
        $pupMapper = Holloway::instance()->getMapper(Pup::class);    // The pup mapper fixture uses soft deletes
        $pupMapper->registerPersistenceEvent('removing', fn($pup) => false);

        // when
        $removed = $pupMapper->remove($pupMapper->find(1));

        // then
        $this->assertFalse($removed);
        $this->assertCount(6, $pupMapper->get());
    }

    /** @test */
    public function a_restoring_listener_that_returns_false_cancels_the_restore()
    {
        // given
        $this->buildFixtures();
        $pupMapper = Holloway::instance()->getMapper(Pup::class);
        $pupMapper->remove($pupMapper->find(1));
        $pupMapper->registerPersistenceEvent('restoring', fn($pup) => false);

        // when
        $restored = $pupMapper->restore($pupMapper->onlyTrashed()->find(1));

        // then
        $this->assertFalse($restored);
        $this->assertCount(5, $pupMapper->get());
    }

    /** @test */
    public function listeners_that_return_nothing_do_not_cancel_operations()
    {
        // given
        $this->buildFixtures();
        $pupMapper = Holloway::instance()->getMapper(Pup::class);
        $pupMapper->registerPersistenceEvent('storing', function($pup) {});
        $pupMapper->registerPersistenceEvent('creating', function($pup) {});

        // when
        $stored = $pupMapper->store(new Pup(Holloway::instance()->getMapper(Pack::class)->find(2), 'Snowball', 'Adams', 'white'));

        // then
        $this->assertTrue($stored);
        $this->assertNotNull($pupMapper->where('first_name', 'Snowball')->first());
    }

    /** @test */
    public function listeners_for_events_fired_after_an_operation_cannot_cancel_it()
    {
        // given
        $this->buildFixtures();
        $pupMapper = Holloway::instance()->getMapper(Pup::class);
        $pupMapper->registerPersistenceEvent('created', fn($pup) => false);
        $pupMapper->registerPersistenceEvent('stored', fn($pup) => false);

        // when
        $stored = $pupMapper->store(new Pup(Holloway::instance()->getMapper(Pack::class)->find(2), 'Snowball', 'Adams', 'white'));

        // then
        $this->assertTrue($stored);
        $this->assertNotNull($pupMapper->where('first_name', 'Snowball')->first());
    }

    /** @test */
    public function a_listener_for_a_before_event_that_returns_a_value_stops_the_remaining_listeners_for_that_event()
    {
        // given (this matches Eloquent's model events)
        $this->buildFixtures();
        $pupMapper = Holloway::instance()->getMapper(Pup::class);
        $ran = [];
        $pupMapper->registerPersistenceEvent('storing', function($pup) use (&$ran) { $ran[] = 'first'; return true; });
        $pupMapper->registerPersistenceEvent('storing', function($pup) use (&$ran) { $ran[] = 'second'; });

        // when
        $pupMapper->store($pupMapper->find(1));

        // then
        $this->assertSame(['first'], $ran);
    }

    /** @test */
    public function after_events_run_every_listener_regardless_of_what_they_return()
    {
        // given
        $this->buildFixtures();
        $pupMapper = Holloway::instance()->getMapper(Pup::class);
        $ran = [];
        $pupMapper->registerPersistenceEvent('stored', function($pup) use (&$ran) { $ran[] = 'first'; return true; });
        $pupMapper->registerPersistenceEvent('stored', function($pup) use (&$ran) { $ran[] = 'second'; });

        // when
        $pupMapper->store($pupMapper->find(1));

        // then
        $this->assertSame(['first', 'second'], $ran);
    }

    /** @test */
    public function a_holloway_wildcard_listener_receives_mapper_events_under_namespaced_names()
    {
        // given
        $this->buildFixtures();
        $dispatcher = new Dispatcher;
        Mapper::setEventManager($dispatcher);
        $names = [];
        $dispatcher->listen('holloway.*', function($eventName, $payload) use (&$names) { $names[] = $eventName; });
        $unrelated = [];
        $dispatcher->listen('eloquent.*', function($eventName, $payload) use (&$unrelated) { $unrelated[] = $eventName; });

        // when
        Holloway::instance()->getMapper(Pup::class)->store(new Pup(Holloway::instance()->getMapper(Pack::class)->find(2), 'Snowball', 'Adams', 'white'));

        // then
        $this->assertSame([
            'holloway.storing: ' . Pup::class,
            'holloway.creating: ' . Pup::class,
            'holloway.created: ' . Pup::class,
            'holloway.stored: ' . Pup::class,
        ], $names);
        $this->assertSame([], $unrelated);
    }

    /** @test */
    public function the_service_provider_sends_mapper_events_through_the_applications_event_dispatcher()
    {
        // given
        $this->buildFixtures();
        $app = new Container;
        $app['events'] = new Dispatcher($app);
        $app['db'] = Mapper::getConnectionResolver();

        $received = [];
        $app['events']->listen('holloway.created: ' . Pup::class, function($pup) use (&$received) { $received[] = $pup; });

        // when
        (new HollowayServiceProvider($app))->boot();

        $pupMapper = Holloway::instance()->getMapper(Pup::class);
        $pup = new Pup(Holloway::instance()->getMapper(Pack::class)->find(2), 'Snowball', 'Adams', 'white');
        $pupMapper->store($pup);

        // then
        $this->assertSame([$pup], $received);
    }
}
