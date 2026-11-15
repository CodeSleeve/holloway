<?php

namespace CodeSleeve\Holloway\Tests\Integration;

use BadMethodCallException;
use CodeSleeve\Holloway\Holloway;
use CodeSleeve\Holloway\Tests\Fixtures\Entities\{Pack, Pup};
use CodeSleeve\Holloway\Tests\Fixtures\Mappers\PackWithConstrainableCollarsMapper;
use CodeSleeve\Holloway\Tests\Helpers\CanBuildTestFixtures;

class RelationConstraintsTest extends TestCase
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
            PackWithConstrainableCollarsMapper::class,
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\PupFoodMapper',
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\PupMapper',
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\UserMapper',
        ]);
    }

    /**
     * @return array
     */
    public static function relationshipsOfEachType() : array
    {
        return [
            'a hasMany'       => [Pack::class, 'pups'],
            'a belongsTo'     => [Pup::class, 'pack'],
            'a belongsToMany' => [Pup::class, 'pupFoods'],
        ];
    }

    /**
     * @dataProvider relationshipsOfEachType
     * @test
     */
    public function it_throws_when_loads_are_changed_in_the_constraints_of_a_relationship(string $entityName, string $relationship)
    {
        // given
        $this->buildFixtures();

        // then
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage("with() can't be called in the constraints of a relationship");

        // when
        Holloway::instance()->getMapper($entityName)->with([$relationship => fn($query) => $query->with('collar')])->find(1);
    }

    /**
     * @return array
     */
    public static function methodsThatChangeLoads() : array
    {
        return [
            'with'                => ['with', ['collar']],
            'without'             => ['without', ['collar']],
            'withoutDefaultLoads' => ['withoutDefaultLoads', []],
        ];
    }

    /**
     * @dataProvider methodsThatChangeLoads
     * @test
     */
    public function it_throws_for_each_method_that_changes_loads_in_the_constraints_of_a_relationship(string $method, array $arguments)
    {
        // given
        $this->buildFixtures();

        // then
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage("$method() can't be called in the constraints of a relationship");

        // when
        Holloway::instance()->getMapper(Pack::class)->with(['pups' => fn($query) => $query->$method(...$arguments)])->find(1);
    }

    /** @test */
    public function it_still_allows_the_other_query_methods_in_the_constraints_of_a_relationship()
    {
        // given
        $this->buildFixtures();

        // when
        $pack = Holloway::instance()->getMapper(Pack::class)
            ->with(['pups' => fn($query) => $query->where('coat', 'white')->orderBy('first_name')])
            ->find(2);

        // then
        $this->assertCount(1, $pack->pups);
    }

    /** @test */
    public function it_passes_the_constraints_of_a_custom_relationship_to_its_load_closure()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(Pack::class);

        // when
        $constrained = $mapper->with(['collars' => fn($query) => $query->where('collars.color', 'blue')])->find(1);
        $unconstrained = $mapper->with('collars')->find(1);

        // then
        $this->assertCount(1, $constrained->collars);
        $this->assertCount(4, $unconstrained->collars);
    }
}