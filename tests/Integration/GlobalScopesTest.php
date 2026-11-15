<?php

namespace CodeSleeve\Holloway\Tests\Integration;

use InvalidArgumentException;
use Illuminate\Database\Capsule\Manager as Capsule;
use CodeSleeve\Holloway\{Holloway, Mapper, SoftDeletingScope};
use CodeSleeve\Holloway\Tests\Fixtures\Entities\Pup;
use CodeSleeve\Holloway\Tests\Fixtures\Mappers\ScopedPupMapper;
use CodeSleeve\Holloway\Tests\Helpers\CanBuildTestFixtures;

class GlobalScopesTest extends TestCase
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
            ScopedPupMapper::class,
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\UserMapper',
        ]);
    }

    public function tearDown() : void
    {
        try {
            // Global scopes are static, so take away every one that a test added.
            foreach (array_keys($this->pups()->getGlobalScopes()) as $identifier) {
                ScopedPupMapper::removeGlobalScope((string) $identifier);
            }
        } finally {
            parent::tearDown();
            Holloway::instance()->flushEntityCache();
        }
    }

    /** @test */
    public function it_applies_a_global_scope_that_is_a_closure()
    {
        // given
        $this->buildFixtures();
        ScopedPupMapper::addGlobalScope(fn($builder) => $builder->where('coat', 'white'));

        // when
        $pups = $this->pups()->get();

        // then
        $this->assertSame(['Lucky'], $pups->map(fn($pup) => $pup->first_name)->all());
    }

    /** @test */
    public function it_applies_a_named_global_scope_that_can_be_removed_by_its_name()
    {
        // given
        $this->buildFixtures();
        $implementation = fn($builder) => $builder->where('coat', 'white');
        ScopedPupMapper::addGlobalScope('white', $implementation);

        // then
        $this->assertTrue(ScopedPupMapper::hasGlobalScope('white'));
        $this->assertSame($implementation, ScopedPupMapper::getGlobalScope('white'));
        $this->assertCount(1, $this->pups()->get());
        $this->assertCount(6, $this->pups()->newQueryWithoutScope('white')->get());

        // when
        ScopedPupMapper::removeGlobalScope('white');

        // then
        $this->assertFalse(ScopedPupMapper::hasGlobalScope('white'));
        $this->assertCount(6, $this->pups()->get());
    }

    /** @test */
    public function it_registers_a_global_scope_object_under_its_class_name()
    {
        // given
        $this->buildFixtures();
        $scope = new SoftDeletingScope;
        ScopedPupMapper::addGlobalScope($scope);
        Capsule::table('pups')->where('id', 1)->update(['deleted_at' => '2020-01-01 00:00:00']);

        // then
        $this->assertTrue(ScopedPupMapper::hasGlobalScope($scope));
        $this->assertSame($scope, ScopedPupMapper::getGlobalScope(SoftDeletingScope::class));
        $this->assertCount(5, $this->pups()->get());

        // when
        ScopedPupMapper::removeGlobalScope($scope);

        // then
        $this->assertFalse(ScopedPupMapper::hasGlobalScope($scope));
        $this->assertCount(6, $this->pups()->get());
    }

    /** @test */
    public function it_ignores_the_removal_of_a_global_scope_that_was_never_added()
    {
        // when
        ScopedPupMapper::removeGlobalScope('never-added');

        // then
        $this->assertSame([], $this->pups()->getGlobalScopes());
    }

    /** @test */
    public function it_throws_when_a_global_scope_is_a_name_without_an_implementation()
    {
        // then
        $this->expectException(InvalidArgumentException::class);

        // when
        ScopedPupMapper::addGlobalScope('named-but-empty');
    }

    /** @test */
    public function it_keeps_the_global_scopes_of_each_mapper_separate()
    {
        // given
        ScopedPupMapper::addGlobalScope('white', fn($builder) => $builder->where('coat', 'white'));

        // then
        $this->assertTrue(ScopedPupMapper::hasGlobalScope('white'));
        $this->assertFalse(\CodeSleeve\Holloway\Tests\Fixtures\Mappers\PupMapper::hasGlobalScope('white'));
    }

    /** @test */
    public function it_adds_the_soft_deleting_macros_to_the_queries_of_a_mapper_that_has_the_scope()
    {
        // given
        $this->buildFixtures();
        ScopedPupMapper::addGlobalScope(new SoftDeletingScope);
        Capsule::table('pups')->where('id', 1)->update(['deleted_at' => '2020-01-01 00:00:00']);

        // then
        $this->assertCount(5, $this->pups()->get());
        $this->assertCount(5, $this->pups()->withoutTrashed()->get());
        $this->assertCount(6, $this->pups()->withTrashed()->get());
        $this->assertCount(1, $this->pups()->onlyTrashed()->get());
    }

    /** @test */
    public function it_qualifies_the_deleted_at_column_when_a_soft_delete_joins_another_table()
    {
        // given
        $this->buildFixtures();
        ScopedPupMapper::addGlobalScope(new SoftDeletingScope);

        // when
        $deleted = $this->pups()->join('packs', 'pups.pack_id', '=', 'packs.id')->where('packs.name', 'Adams Pack')->delete();

        // then
        $this->assertSame(2, $deleted);
        $this->assertSame(['Lucky', 'Duchess'], Capsule::table('pups')->whereNotNull('deleted_at')->orderBy('id')->pluck('first_name')->all());
    }

    /**
     * @return Mapper
     */
    private function pups() : Mapper
    {
        return Holloway::instance()->getMapper(Pup::class);
    }
}
