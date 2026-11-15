<?php

namespace CodeSleeve\Holloway\Tests\Integration;

use Closure;
use InvalidArgumentException;
use stdClass;
use CodeSleeve\Holloway\Holloway;
use CodeSleeve\Holloway\Exceptions\UknownRelationshipException;
use CodeSleeve\Holloway\Relationships\Custom;
use CodeSleeve\Holloway\Tests\Fixtures\Entities\{ExtendedPack, Pup};
use CodeSleeve\Holloway\Tests\Helpers\CanBuildTestFixtures;

class CustomRelationshipsTest extends TestCase
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
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\ExtendedPackMapper',
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\PupFoodMapper',
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\PupMapper',
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\UserMapper',
        ]);
    }

    /** @test */
    public function it_loads_a_custom_relationship_that_returns_a_single_entity()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(ExtendedPack::class);

        // when
        $packs = $mapper->with('latestPup')->get();

        // then
        $this->assertSame(['Trinka', 'Duchess'], $packs->sortBy('id')->map(fn($pack) => $pack->latestPup->first_name)->values()->all());
        $this->assertInstanceOf(Pup::class, $packs->first()->latestPup);
    }

    /** @test */
    public function it_loads_a_custom_relationship_that_is_mapped_by_a_callable_instead_of_an_entity()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(ExtendedPack::class);

        // when
        $pack = $mapper->with('pupNames')->find(2);

        // then
        $this->assertSame(['DUCHESS', 'LUCKY'], $pack->pupNames->all());
    }

    /** @test */
    public function it_describes_its_custom_relationships()
    {
        // given
        $mapper = Holloway::instance()->getMapper(ExtendedPack::class);

        // when
        $latestPup = $mapper->getRelationship('latestPup');
        $pupNames = $mapper->getRelationship('pupNames');

        // then
        $this->assertInstanceOf(Custom::class, $latestPup);
        $this->assertTrue($latestPup->shouldLimitToOne());
        $this->assertSame(Pup::class, $latestPup->getEntityName());
        $this->assertNull($latestPup->getMap());

        $this->assertFalse($pupNames->shouldLimitToOne());
        $this->assertNull($pupNames->getEntityName());
        $this->assertInstanceOf(Closure::class, $pupNames->getMap());
    }

    /** @test */
    public function it_keeps_the_records_it_loaded()
    {
        // given
        $this->buildFixtures();
        $relationship = clone Holloway::instance()->getMapper(ExtendedPack::class)->getRelationship('pupNames');

        // when
        $relationship->load(collect([(object) ['id' => 1]]));

        // then
        $this->assertCount(4, $relationship->getData());
    }

    /** @test */
    public function it_defines_custom_relationships_when_the_mappers_are_registered_and_maps_them_with_a_closure()
    {
        // given
        $this->buildFixtures();
        self::setUpBeforeClass();       // Registering inside the test is what lets its definitions be counted as covered.
        $mapper = Holloway::instance()->getMapper(ExtendedPack::class);

        try {
            // when
            $mapper->customOne(
                'firstName',
                fn($query, $packs) => $query->from('pups')->whereIn('pack_id', $packs->pluck('id'))->orderBy('id')->get(),
                fn(stdClass $pack, stdClass $pup) => $pack->id == $pup->pack_id,
                fn(stdClass $pup) => $pup->first_name
            );
            $pack = $mapper->with('firstName')->find(2);

            // then
            $this->assertSame('Lucky', $pack->firstName);
            $this->assertInstanceOf(Closure::class, $mapper->getRelationship('firstName')->getMap());
        } finally {
            self::setUpBeforeClass();   // The relationship added above belongs to this test only.
        }
    }

    /** @test */
    public function it_has_no_data_before_it_is_loaded()
    {
        // given
        $mapper = Holloway::instance()->getMapper(ExtendedPack::class);

        // then
        $this->assertNull($mapper->getRelationship('pupNames')->getData());
        $this->assertNull($mapper->getRelationship('pups')->getData());
    }

    /** @test */
    public function it_knows_which_relationships_a_mapper_has()
    {
        // given
        $mapper = Holloway::instance()->getMapper(ExtendedPack::class);

        // then
        $this->assertTrue($mapper->hasRelationship('pups'));
        $this->assertFalse($mapper->hasRelationship('nonexistent'));
        $this->assertSame(['pups', 'latestPup', 'pupNames'], array_keys($mapper->getRelationships()));
    }

    /** @test */
    public function it_throws_when_a_relationship_that_isnt_defined_is_requested()
    {
        // then
        $this->expectException(UknownRelationshipException::class);
        $this->expectExceptionMessage("The nonexistent relationship hasn't been defined");

        // when
        Holloway::instance()->getMapper(ExtendedPack::class)->getRelationship('nonexistent');
    }

    /** @test */
    public function it_throws_when_a_custom_relationship_has_neither_a_map_nor_an_entity()
    {
        // then
        $this->expectException(InvalidArgumentException::class);

        // when
        Holloway::instance()->getMapper(ExtendedPack::class)->custom('broken', fn($query, $records) => [], fn($record, $related) => true, 123);
    }
}
