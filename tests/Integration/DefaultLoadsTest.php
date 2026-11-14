<?php

namespace CodeSleeve\Holloway\Tests\Integration;

use Illuminate\Database\Capsule\Manager as Capsule;
use CodeSleeve\Holloway\Builder;
use CodeSleeve\Holloway\Holloway;
use CodeSleeve\Holloway\Exceptions\CircularDefaultLoadsException;
use CodeSleeve\Holloway\Tests\Fixtures\Entities\{Pack, Pup, Collar};
use CodeSleeve\Holloway\Tests\Fixtures\Mappers\{CollarWithCompanyMapper, PackWithPupsCollarMapper, PackWithPupsMapper};
use CodeSleeve\Holloway\Tests\Fixtures\Mappers\{PupWithCollarCompanyMapper, PupWithCollarMapper, PupWithOrangeCollarMapper, PupWithPackMapper};
use CodeSleeve\Holloway\Tests\Helpers\CanBuildTestFixtures;

class DefaultLoadsTest extends TestCase
{
    use CanBuildTestFixtures;

    /** @test */
    public function it_loads_the_default_loads_of_a_related_mapper()
    {
        // given
        $this->buildFixtures();
        $this->register(PupWithCollarMapper::class);

        // when
        $pack = Holloway::instance()->getMapper(Pack::class)->with('pups')->find(1);

        // then
        $this->assertCount(4, $pack->pups);
        $pack->pups->each(fn($pup) => $this->assertInstanceOf(Collar::class, $pup->collar));
    }

    /** @test */
    public function it_loads_default_loads_through_each_relationship_that_leads_to_them()
    {
        // given
        $this->buildFixtures();
        $this->register(PupWithCollarMapper::class, PackWithPupsMapper::class);

        // when
        $pup = Holloway::instance()->getMapper(Pup::class)->with('pack')->find(1);

        // then
        $this->assertInstanceOf(Pack::class, $pup->pack);
        $this->assertCount(4, $pup->pack->pups);
        $pup->pack->pups->each(fn($pup) => $this->assertInstanceOf(Collar::class, $pup->collar));
    }

    /** @test */
    public function it_loads_the_default_loads_of_a_related_mapper_with_one_query_per_relationship()
    {
        // given
        $this->buildFixtures();
        $this->register(PupWithCollarMapper::class);
        Capsule::connection()->enableQueryLog();
        Capsule::connection()->flushQueryLog();

        // when
        Holloway::instance()->getMapper(Pack::class)->with('pups')->find(1);

        // then
        $this->assertCount(3, Capsule::connection()->getQueryLog());     // packs, pups and collars.
    }

    /** @test */
    public function it_keeps_adding_the_default_loads_of_the_mappers_that_the_defaults_lead_to()
    {
        // given
        $this->register(PupWithCollarMapper::class, CollarWithCompanyMapper::class);

        // when
        $tree = $this->treeFor($this->query()->with('pups'));

        // then
        $this->assertSame(['company'], array_keys($tree['pups']['children']['collar']['children']));
    }

    /** @test */
    public function it_adds_a_default_load_that_is_nested()
    {
        // given
        $this->register(PupWithCollarCompanyMapper::class);

        // when
        $tree = $this->treeFor($this->query()->with('pups'));

        // then
        $this->assertSame(['collar'], array_keys($tree['pups']['children']));
        $this->assertSame(['company'], array_keys($tree['pups']['children']['collar']['children']));
    }

    /**
     * @return array
     */
    public static function orderOfAParentAndItsNestedLoad() : array
    {
        return [
            'the parent is asked for first'  => [true],
            'the parent is asked for second' => [false],
        ];
    }

    /**
     * @dataProvider orderOfAParentAndItsNestedLoad
     * @test
     */
    public function it_never_lets_a_default_load_replace_one_that_was_asked_for(bool $parentFirst)
    {
        // given
        $this->register(PupWithCollarMapper::class);
        $constraints = fn($query) => $query->where('color', 'orange');
        $loads = $parentFirst ? ['pups', 'pups.collar' => $constraints] : ['pups.collar' => $constraints, 'pups'];

        // when
        $tree = $this->treeFor($this->query()->with($loads));

        // then
        $this->assertSame($constraints, $tree['pups']['children']['collar']['constraints']);
    }

    /**
     * @dataProvider orderOfAParentAndItsNestedLoad
     * @test
     */
    public function it_keeps_the_constraints_of_a_default_load_that_a_nested_load_passes_through(bool $parentFirst)
    {
        // given
        $this->register(PupWithOrangeCollarMapper::class);
        $loads = $parentFirst ? ['pups', 'pups.collar.company'] : ['pups.collar.company', 'pups'];

        // when
        $tree = $this->treeFor($this->query()->with($loads));
        $query = Holloway::instance()->getMapper(Collar::class)->newQuery();
        $tree['pups']['children']['collar']['constraints']($query);

        // then
        $this->assertSame(['orange'], array_column($query->getQuery()->wheres, 'value'));
        $this->assertSame(['company'], array_keys($tree['pups']['children']['collar']['children']));
    }

    /** @test */
    public function it_keeps_the_nested_default_loads_of_a_mapper_when_a_bare_load_is_added()
    {
        // given
        $this->register(PackWithPupsCollarMapper::class);

        // when
        $tree = $this->treeFor($this->query()->with('pups'));

        // then
        $this->assertSame(['collar'], array_keys($tree['pups']['children']));
    }

    /** @test */
    public function it_does_not_add_default_loads_beneath_a_custom_relationship()
    {
        // given
        $this->register(CollarWithCompanyMapper::class);

        // when
        $tree = $this->treeFor($this->query()->with('collars'));

        // then
        $this->assertSame([], $tree['collars']['children']);
    }

    /** @test */
    public function it_does_not_report_default_loads_of_a_related_mapper_as_loads_of_the_query()
    {
        // given
        $this->register(PupWithCollarMapper::class);

        // when
        $builder = $this->query()->with('pups');

        // then
        $this->assertSame(['pups'], array_keys($builder->getLoads()));
    }

    /** @test */
    public function it_throws_when_default_loads_lead_back_around_to_themselves()
    {
        // given
        $this->register(PackWithPupsMapper::class, PupWithPackMapper::class);

        // then
        $this->expectException(CircularDefaultLoadsException::class);
        $this->expectExceptionMessageMatches('/PupWithPackMapper -> .*PackWithPupsMapper -> .*PupWithPackMapper\.$/');

        // when
        $this->treeFor($this->query());
    }

    /** @test */
    public function it_throws_before_querying_when_the_default_loads_of_the_mapper_being_queried_lead_back_around()
    {
        // given
        $this->buildFixtures();
        $this->register(PackWithPupsMapper::class, PupWithPackMapper::class);

        // then
        $this->expectException(CircularDefaultLoadsException::class);

        // when
        Holloway::instance()->getMapper(Pup::class)->find(1);
    }

    /** @test */
    public function it_does_not_throw_when_a_path_that_was_asked_for_comes_back_around_to_a_mapper()
    {
        // given
        $this->register(PupWithPackMapper::class);

        // when
        $tree = $this->treeFor($this->query()->with('pups.pack.pups'));

        // then
        $this->assertSame(['pack'], array_keys($tree['pups']['children']));
        $this->assertSame(['pups'], array_keys($tree['pups']['children']['pack']['children']));
        $this->assertSame(['pack'], array_keys($tree['pups']['children']['pack']['children']['pups']['children']));
    }

    /**
     * Register the fixture mappers along with any others, which replace the fixture mapper for the same entity.
     *
     * @param  string ...$mapperClasses
     * @return void
     */
    private function register(string ...$mapperClasses) : void
    {
        Holloway::instance()->register([
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\CollarMapper',
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\CompanyMapper',
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\PackMapper',
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\PupFoodMapper',
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\PupMapper',
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\UserMapper',
            ...$mapperClasses,
        ]);
    }

    /**
     * @return Builder
     */
    private function query() : Builder
    {
        return Holloway::instance()->getMapper(Pack::class)->newQuery();
    }

    /**
     * Build the tree for a query and return its nodes.
     *
     * @param  Builder $query
     * @return array
     */
    private function treeFor(Builder $query) : array
    {
        $query->getTree()->initialize();

        return $query->getTree()->getData();
    }
}