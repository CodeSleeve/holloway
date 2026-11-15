<?php

namespace CodeSleeve\Holloway\Tests\Integration;

use BadMethodCallException;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use CodeSleeve\Holloway\{Builder, Holloway, Mapper, SoftDeletingScope};
use CodeSleeve\Holloway\Tests\Fixtures\Entities\{Collar, Pack, Pup};
use CodeSleeve\Holloway\Tests\Helpers\CanBuildTestFixtures;

class BuilderQueryingTest extends TestCase
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

    /** @test */
    public function it_finds_an_entity_or_throws_when_it_doesnt_exist()
    {
        // given
        $this->buildFixtures();

        // when
        $pup = $this->pups()->findOrFail(1);

        // then
        $this->assertInstanceOf(Pup::class, $pup);
        $this->assertSame('Tobias', $pup->first_name);

        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessageMatches('/Pup\] 999/');
        $this->pups()->findOrFail(999);
    }

    /** @test */
    public function it_returns_the_first_entity_or_throws_when_there_isnt_one()
    {
        // given
        $this->buildFixtures();

        // when
        $pup = $this->pups()->where('coat', 'brown')->firstOrFail();

        // then
        $this->assertSame('Trinka', $pup->first_name);

        $this->expectException(ModelNotFoundException::class);
        $this->pups()->where('coat', 'purple')->firstOrFail();
    }

    /** @test */
    public function it_finds_many_entities_by_their_keys()
    {
        // given
        $this->buildFixtures();

        // when
        $pups = $this->pups()->findMany([1, 3, 5]);

        // then
        $this->assertEqualsCanonicalizing(['Tobias', 'Tucker', 'Lucky'], $pups->map(fn($pup) => $pup->first_name)->all());
    }

    /** @test */
    public function it_doesnt_run_a_query_when_it_is_asked_to_find_no_keys()
    {
        // given
        $this->buildFixtures();
        Capsule::connection()->enableQueryLog();
        Capsule::connection()->flushQueryLog();

        // when
        $pups = $this->pups()->findMany([]);

        // then
        $this->assertCount(0, $pups);
        $this->assertCount(0, Capsule::connection()->getQueryLog());
    }

    /** @test */
    public function it_combines_where_clauses_with_or_where()
    {
        // given
        $this->buildFixtures();

        // when
        $pups = $this->pups()->where('coat', 'white')->orWhere('coat', 'mixed')->get();

        // then
        $this->assertEqualsCanonicalizing(['Lucky', 'Duchess'], $pups->map(fn($pup) => $pup->first_name)->all());
    }

    /** @test */
    public function it_keeps_or_where_clauses_from_escaping_the_global_scopes()
    {
        // given
        $this->buildFixtures();
        Capsule::table('pups')->where('id', 6)->update(['deleted_at' => '2020-01-01 00:00:00']);    // Duchess (mixed) is soft deleted

        // when
        $pups = $this->pups()->where('coat', 'white')->orWhere('coat', 'mixed')->get();

        // then
        $this->assertSame(['Lucky'], $pups->map(fn($pup) => $pup->first_name)->all());
    }

    /** @test */
    public function it_calls_local_scopes_by_name_with_their_parameters()
    {
        // given
        $this->buildFixtures();

        // when
        $withParameter = $this->pups()->scopes(['ofCoat' => 'brown'])->get();
        $withParameterList = $this->pups()->scopes(['ofCoat' => ['black']])->get();
        $withoutParameters = Holloway::instance()->getMapper(Collar::class)->scopes(['thatAreOrange'])->get();

        // then
        $this->assertSame(['Trinka'], $withParameter->map(fn($pup) => $pup->first_name)->all());
        $this->assertCount(3, $withParameterList);
        $this->assertCount(2, $withoutParameters);
    }

    /** @test */
    public function it_paginates_entities_without_counting_the_total()
    {
        // given
        $this->buildFixtures();

        // when
        $firstPage = $this->pups()->orderBy('id')->simplePaginate(2);
        $lastPage = $this->pups()->orderBy('id')->simplePaginate(2, ['*'], 'page', 3);
        $defaultPageSize = $this->pups()->orderBy('id')->simplePaginate();

        // then
        $this->assertCount(2, $firstPage->items());
        $this->assertTrue($firstPage->hasMorePages());
        $this->assertSame(['Lucky', 'Duchess'], array_map(fn($pup) => $pup->first_name, $lastPage->items()));
        $this->assertFalse($lastPage->hasMorePages());
        $this->assertCount(6, $defaultPageSize->items());
    }

    /** @test */
    public function it_can_use_a_different_base_query()
    {
        // given
        $this->buildFixtures();
        $builder = $this->pups()->newQuery();
        $query = Capsule::table('pups')->where('coat', 'brown');

        // when
        $pups = $builder->setQuery($query)->get();

        // then
        $this->assertSame($query, $builder->getQuery());
        $this->assertSame(['Trinka'], $pups->map(fn($pup) => $pup->first_name)->all());
    }

    /** @test */
    public function it_can_remove_all_or_some_of_the_global_scopes()
    {
        // given
        $this->buildFixtures();
        Capsule::table('pups')->where('id', 1)->update(['deleted_at' => '2020-01-01 00:00:00']);
        $builder = $this->pups()->newQuery()->withoutGlobalScopes([SoftDeletingScope::class]);

        // when
        $withoutSome = $builder->get();
        $withoutAll = $this->pups()->newQuery()->withoutGlobalScopes(null)->get();

        // then
        $this->assertCount(5, $this->pups()->get());
        $this->assertCount(6, $withoutSome);
        $this->assertCount(6, $withoutAll);
        $this->assertSame([SoftDeletingScope::class], $builder->removedScopes());
    }

    /** @test */
    public function it_exposes_the_data_of_its_tree()
    {
        // given
        $tree = $this->pups()->newQuery()->getTree();

        // when
        $tree->setData(['collar' => []]);

        // then
        $this->assertSame(['collar' => []], $tree->getData());
    }

    /** @test */
    public function it_calls_macros_statically()
    {
        // given
        Builder::macro('holloway_test_doubled', fn(int $number) => $number * 2);
        Builder::macro('holloway_test_shout', 'strtoupper');

        // then
        $this->assertSame(8, Builder::holloway_test_doubled(4));
        $this->assertSame('PUP', Builder::holloway_test_shout('pup'));

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Method holloway_test_missing does not exist.');
        Builder::holloway_test_missing();
    }

    /** @test */
    public function it_calls_macros_on_a_query()
    {
        // given
        $this->buildFixtures();
        Builder::macro('holloway_test_white', function() {
            return $this->where('coat', 'white');
        });

        // when
        $pups = $this->pups()->holloway_test_white()->get();

        // then
        $this->assertSame(['Lucky'], $pups->map(fn($pup) => $pup->first_name)->all());
    }

    /** @test */
    public function it_finds_many_entities_when_find_is_given_an_array_of_keys()
    {
        // given
        $this->buildFixtures();

        // when
        $pups = $this->pups()->find([1, 2]);

        // then
        $this->assertCount(2, $pups);
    }

    /** @test */
    public function it_groups_the_where_clauses_of_a_closure_together()
    {
        // given
        $this->buildFixtures();

        // when
        $pups = $this->pups()
            ->where('pack_id', 1)
            ->where(function($query) {
                $query->where('coat', 'brown')->orWhere('coat', 'white');
            })
            ->get();

        // then
        $this->assertSame(['Trinka'], $pups->map(fn($pup) => $pup->first_name)->all());
    }

    /** @test */
    public function it_loads_nested_relationships_that_share_a_parent()
    {
        // given
        $this->buildFixtures();

        // when
        $pack = Holloway::instance()->getMapper(Pack::class)->with(['pups.collar', 'pups.pack'])->find(1);

        // then
        $this->assertCount(4, $pack->pups);
        $pack->pups->each(function($pup) {
            $this->assertInstanceOf(Collar::class, $pup->collar);
            $this->assertInstanceOf(Pack::class, $pup->pack);
        });
    }

    /** @test */
    public function it_returns_an_empty_page_when_there_is_nothing_to_paginate()
    {
        // given
        $this->buildFixtures();

        // when
        $page = $this->pups()->where('coat', 'purple')->paginate();

        // then
        $this->assertSame(0, $page->total());
        $this->assertCount(0, $page->items());
    }

    /**
     * @return Mapper
     */
    private function pups() : Mapper
    {
        return Holloway::instance()->getMapper(Pup::class);
    }
}
