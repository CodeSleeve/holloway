<?php

namespace CodeSleeve\Holloway\Tests\Integration;

use CodeSleeve\Holloway\Holloway;
use CodeSleeve\Holloway\Builder;
use CodeSleeve\Holloway\Tests\Fixtures\Entities\Pack;

class EagerLoadMergeTest extends TestCase
{
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
     * @return array
     */
    public static function bareAndDottedLoads() : array
    {
        return [
            'a bare load before a dotted one' => [['pups'], ['pups.collar']],
            'a bare load after a dotted one'  => [['pups.collar'], ['pups']],
        ];
    }

    /**
     * @dataProvider bareAndDottedLoads
     * @test
     */
    public function it_keeps_the_nested_loads_no_matter_what_order_a_bare_load_is_given_in(array $first, array $second)
    {
        // when
        $tree = $this->treeFor($this->query()->with($first)->with($second));

        // then
        $this->assertSame(['pups'], array_keys($tree));
        $this->assertSame(['collar'], array_keys($tree['pups']['children']));
    }

    /** @test */
    public function it_replaces_the_constraints_of_a_node_without_dropping_its_children()
    {
        // given
        $constraints = fn($query) => $query->where('id', 1);

        // when
        $tree = $this->treeFor($this->query()->with('pups.collar')->with(['pups' => $constraints]));

        // then
        $this->assertSame($constraints, $tree['pups']['constraints']);
        $this->assertSame(['collar'], array_keys($tree['pups']['children']));
    }

    /** @test */
    public function it_builds_the_whole_path_when_a_relationship_name_is_repeated()
    {
        // when
        $tree = $this->treeFor($this->query()->with('pups.pack.pups.collar'));

        // then
        $this->assertSame(['collar'], array_keys($tree['pups']['children']['pack']['children']['pups']['children']));
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