<?php

namespace CodeSleeve\Holloway\Tests\Integration;

use InvalidArgumentException;
use Faker\Factory as FakerFactory;
use Faker\Generator as Faker;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Collection;
use CodeSleeve\Holloway\{Factory, Holloway, HollowayServiceProvider};
use CodeSleeve\Holloway\Tests\Fixtures\Entities\{Pack, Pup};
use CodeSleeve\Holloway\Tests\Helpers\CanBuildTestFixtures;

class FactoryTest extends TestCase
{
    use CanBuildTestFixtures;

    /**
     * @return  void
     */
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

    /**
     * @return Factory
     */
    private function factory() : Factory
    {
        // There is no directory of factories, so the definition is added here.
        $factory = Factory::construct(FakerFactory::create(), __DIR__ . '/no-factories', Holloway::instance());

        $factory->define(Pup::class, function (Faker $faker) {
            return [
                'pack_id'    => 1,
                'first_name' => $faker->firstName,
                'last_name'  => fn() => 'Factory',
                'coat'       => 'brindle',
            ];
        });

        return $factory;
    }

    /** @test */
    public function it_makes_an_entity_from_a_factory_definition_without_storing_it()
    {
        // given
        $this->buildFixtures();

        // when
        $pup = $this->factory()->of(Pup::class)->make();

        // then
        $this->assertInstanceOf(Pup::class, $pup);
        $this->assertSame('brindle', $pup->coat);
        $this->assertSame('Factory', $pup->last_name);
        $this->assertSame(6, Capsule::table('pups')->count());
    }

    /** @test */
    public function it_lets_the_attributes_of_a_definition_be_overridden()
    {
        // when
        $pup = $this->factory()->of(Pup::class)->make(['coat' => 'spotted']);

        // then
        $this->assertSame('spotted', $pup->coat);
    }

    /** @test */
    public function it_makes_as_many_entities_as_it_is_asked_to()
    {
        // when
        $pups = $this->factory()->of(Pup::class)->times(3)->make();

        // then
        $this->assertInstanceOf(Collection::class, $pups);
        $this->assertCount(3, $pups);
    }

    /** @test */
    public function it_stores_the_entities_it_creates()
    {
        // given
        $this->buildFixtures();

        // when
        $pup = $this->factory()->of(Pup::class)->create(['first_name' => 'Fixture']);

        // then
        $this->assertNotNull($pup->id);
        $this->assertSame('Fixture', Capsule::table('pups')->where('id', $pup->id)->value('first_name'));
        $this->assertSame(7, Capsule::table('pups')->count());
    }

    /** @test */
    public function it_throws_when_there_is_no_definition_for_an_entity()
    {
        // then
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to locate factory for [' . Pack::class . ']');

        // when
        $this->factory()->of(Pack::class)->make();
    }

    /** @test */
    public function the_service_provider_registers_without_needing_anything()
    {
        // when
        $result = (new HollowayServiceProvider(new Container))->register();

        // then
        $this->assertNull($result);
    }
}
