<?php

namespace CodeSleeve\Holloway\Tests\Integration\Relationships;

use stdClass;
use CodeSleeve\Holloway\Holloway;
use CodeSleeve\Holloway\Tests\Fixtures\Entities\{Pack, Pup};
use CodeSleeve\Holloway\Tests\Fixtures\Mappers\PackMapper;
use CodeSleeve\Holloway\Tests\Helpers\{CanBuildTestFixtures, RegistersFixtureMappers};
use CodeSleeve\Holloway\Tests\Integration\TestCase;

/**
 * A pack mapper with a custom relationship that has a count closure (the fixture PackMapper's custom
 * relationship has none), which counts the pack's black pups.
 */
class CountableCustomPackMapper extends PackMapper
{
    public function defineRelations() : void
    {
        parent::defineRelations();

        $this->custom(
            'blackPups',
            fn($query, $packs) => $query->from('pups')->whereIn('pack_id', $packs->pluck('id'))->where('coat', 'black')->get(),
            fn(stdClass $pack, stdClass $pup) => $pack->id == $pup->pack_id,
            Pup::class,
            false,
            fn($query, string $parentTable, string $parentKey) => $query->from('pups')
                ->selectRaw('count(*)')
                ->whereColumn('pups.pack_id', '=', "{$parentTable}.{$parentKey}")
                ->where('coat', 'black')
        );
    }
}

class WithCountCustomTest extends TestCase
{
    use CanBuildTestFixtures, RegistersFixtureMappers;

    public static function setUpBeforeClass(): void
    {
        self::registerFixtureMappers(CountableCustomPackMapper::class);
    }

    public static function tearDownAfterClass(): void
    {
        self::registerFixtureMappers();
    }

    /** @test */
    public function it_counts_a_custom_relationship_through_its_count_closure()
    {
        // given: Bennett Pack has three black pups (Tobias, Tyler, Tucker), Adams Pack has none
        $this->buildFixtures();
        $packMapper = Holloway::instance()->getMapper(Pack::class);

        // when
        $packs = $packMapper->withCount('blackPups')->orderBy('id')->get();

        // then
        $this->assertEquals(3, $packs[0]->black_pups_count);
        $this->assertEquals(0, $packs[1]->black_pups_count);
    }

    /** @test */
    public function it_applies_constraints_to_a_custom_relationships_count()
    {
        $this->buildFixtures();
        $packMapper = Holloway::instance()->getMapper(Pack::class);

        $packs = $packMapper->withCount([
            'blackPups' => fn($query) => $query->where('first_name', 'Tobias'),
        ])->orderBy('id')->get();

        $this->assertEquals(1, $packs[0]->black_pups_count);
        $this->assertEquals(0, $packs[1]->black_pups_count);
    }

    /** @test */
    public function an_or_constraint_stays_inside_the_custom_relationships_count()
    {
        $this->buildFixtures();
        $packMapper = Holloway::instance()->getMapper(Pack::class);

        // Lucky (Adams Pack) is white, so the count closure's "black" clause must still apply to her.
        $packs = $packMapper->withCount([
            'blackPups' => fn($query) => $query->where('first_name', 'Tobias')->orWhere('first_name', 'Lucky'),
        ])->orderBy('id')->get();

        $this->assertEquals(1, $packs[0]->black_pups_count);
        $this->assertEquals(0, $packs[1]->black_pups_count);
    }

    /** @test */
    public function a_custom_relationship_without_a_count_closure_says_how_to_add_one()
    {
        $this->buildFixtures();
        $packMapper = Holloway::instance()->getMapper(Pack::class);

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Mapper::custom()');

        $packMapper->withCount('collars')->get();   // the fixture's custom "collars" relationship has no count closure
    }
}
