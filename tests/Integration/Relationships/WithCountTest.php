<?php

namespace CodeSleeve\Holloway\Tests\Integration\Relationships;

use CodeSleeve\Holloway\Holloway;
use CodeSleeve\Holloway\Tests\Fixtures\Entities\{Pup, Pack, Collar, User, Company, PupFood};
use CodeSleeve\Holloway\Tests\Fixtures\Mappers\{PupMapper, PackMapper, CollarMapper, UserMapper, CompanyMapper, PupFoodMapper};
use CodeSleeve\Holloway\Tests\Helpers\CanBuildTestFixtures;
use CodeSleeve\Holloway\Tests\Integration\TestCase;

class WithCountTest extends TestCase
{
    use CanBuildTestFixtures;

    /**
     * Register all mappers before running tests.
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
    public function it_counts_has_many_relationships()
    {
        // given: Two packs - Bennett Pack has 4 pups, Adams Pack has 2 pups
        $this->buildFixtures();
        $packMapper = Holloway::instance()->getMapper(Pack::class);

        // when: Load packs with pups count
        $packs = $packMapper->withCount('pups')->get();

        // then: Counts should match the fixture data
        $bennettPack = $packs->firstWhere('name', 'Bennett Pack');
        $adamsPack = $packs->firstWhere('name', 'Adams Pack');

        $this->assertEquals(4, $bennettPack->pups_count, 'Bennett Pack should have 4 pups');
        $this->assertEquals(2, $adamsPack->pups_count, 'Adams Pack should have 2 pups');
    }

    /** @test */
    public function it_counts_has_many_relationships_with_zero_results()
    {
        // given: A pack with no pups
        $this->buildFixtures();
        $packMapper = Holloway::instance()->getMapper(Pack::class);
        
        // Create a third pack with no pups
        \Illuminate\Database\Capsule\Manager::table('packs')->insert([
            ['id' => 3, 'name' => 'Empty Pack']
        ]);

        // when: Load packs with pups count
        $packs = $packMapper->withCount('pups')->get();

        // then: Empty pack should have 0 count
        $emptyPack = $packs->firstWhere('name', 'Empty Pack');
        $this->assertEquals(0, $emptyPack->pups_count, 'Empty Pack should have 0 pups');
    }

    /** @test */
    public function it_counts_has_many_relationships_with_constraints()
    {
        // given: Bennett Pack has 3 black pups and 1 brown pup
        $this->buildFixtures();
        $packMapper = Holloway::instance()->getMapper(Pack::class);

        // when: Count only black pups
        $packs = $packMapper->withCount([
            'pups' => function($query) {
                $query->where('coat', 'black');
            }
        ])->get();

        // then: Bennett Pack should have 3 black pups
        $bennettPack = $packs->firstWhere('name', 'Bennett Pack');
        $this->assertEquals(3, $bennettPack->pups_count, 'Bennett Pack should have 3 black pups');
    }

    /** @test */
    public function it_counts_multiple_relationships_on_same_query()
    {
        // given: Company has collars and pup foods
        $this->buildFixtures();
        $companyMapper = Holloway::instance()->getMapper(Company::class);

        // when: Count both collars and pupFoods
        $companies = $companyMapper->withCount(['collars', 'pupFoods'])->get();

        // then: Both counts should be present
        $diamond = $companies->firstWhere('name', 'Diamond Pet Foods and Accessories');
        
        $this->assertEquals(6, $diamond->collars_count, 'Diamond should have 6 collars');
        $this->assertEquals(2, $diamond->pup_foods_count, 'Diamond should have 2 pup foods');
    }

    /** @test */
    public function it_counts_has_one_relationships_when_relationship_exists()
    {
        // given: Pups with collars
        $this->buildFixtures();
        $pupMapper = Holloway::instance()->getMapper(Pup::class);

        // when: Load pups with collar count
        $pups = $pupMapper->withCount('collar')->get();

        // then: Each pup should have collar_count of 1 (all pups in fixtures have collars)
        foreach ($pups as $pup) {
            $this->assertEquals(1, $pup->collar_count, "Pup {$pup->first_name} should have 1 collar");
        }
    }

    /** @test */
    public function it_counts_has_one_relationships_when_relationship_does_not_exist()
    {
        // given: A pup without a collar
        $this->buildFixtures();
        $pupMapper = Holloway::instance()->getMapper(Pup::class);
        
        // Create a pup without a collar
        \Illuminate\Database\Capsule\Manager::table('pups')->insert([
            ['id' => 7, 'pack_id' => 1, 'first_name' => 'Naked', 'last_name' => 'Pup', 'coat' => 'white', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]
        ]);

        // when: Load pups with collar count
        $pups = $pupMapper->withCount('collar')->get();

        // then: Pup without collar should have count of 0
        $nakedPup = $pups->firstWhere('first_name', 'Naked');
        $this->assertEquals(0, $nakedPup->collar_count, 'Pup without collar should have 0 count');
    }

    /** @test */
    public function it_counts_belongs_to_relationships()
    {
        // given: Multiple pups belonging to packs
        $this->buildFixtures();
        $pupMapper = Holloway::instance()->getMapper(Pup::class);

        // when: Load pups with pack count
        $pups = $pupMapper->withCount('pack')->get();

        // then: Each pup should have pack_count = 1 (every pup belongs to exactly one pack)
        foreach ($pups as $pup) {
            $this->assertEquals(1, $pup->pack_count, "Pup {$pup->first_name} should have pack_count of 1");
        }
        
        // Verify we got the expected number of pups
        $this->assertCount(6, $pups, 'Should have 6 pups total');
    }

    /** @test */
    public function it_counts_belongs_to_relationships_with_correct_foreign_key()
    {
        // given: Collars belonging to pups
        $this->buildFixtures();
        $collarMapper = Holloway::instance()->getMapper(Collar::class);

        // when: Load collars with pup count
        $collars = $collarMapper->withCount('pup')->get();

        // then: Each collar should have pup_count = 1
        foreach ($collars as $collar) {
            $this->assertEquals(1, $collar->pup_count, "Collar {$collar->id} should have pup_count of 1");
        }
    }

    /** @test */
    public function it_counts_belongs_to_relationships_verifying_sql_correctness()
    {
        // given: Pups belonging to packs
        $this->buildFixtures();
        $pupMapper = Holloway::instance()->getMapper(Pup::class);

        // when: Build the query with pack count
        $query = $pupMapper->withCount('pack');
        $sql = $query->toSql();

        // then: The SQL should contain the correct whereColumn clause
        // For BelongsTo, it should match: related_table.id = parent_table.foreign_key
        // In this case: packs.id = pups.pack_id
        $this->assertStringContainsString('packs', $sql, 'SQL should reference packs table');
        $this->assertStringContainsString('pups', $sql, 'SQL should reference pups table');
        
        // Execute and verify counts are correct
        $pups = $query->get();
        foreach ($pups as $pup) {
            $this->assertEquals(1, $pup->pack_count, "Pup {$pup->first_name} should have pack_count of 1");
        }
    }

    /** @test */
    public function it_counts_belongs_to_many_relationships()
    {
        // given: Users with multiple pups
        $this->buildFixtures();
        $userMapper = Holloway::instance()->getMapper(User::class);

        // when: Load users with pups count
        $users = $userMapper->withCount('pups')->get();

        // then: Travis should have 5 pups, Marilyn should have 5 pups
        $travis = $users->firstWhere('first_name', 'Travis');
        $marilyn = $users->firstWhere('first_name', 'Marilyn');

        $this->assertEquals(5, $travis->pups_count, 'Travis should have 5 pups');
        $this->assertEquals(5, $marilyn->pups_count, 'Marilyn should have 5 pups');
    }

    /** @test */
    public function it_counts_belongs_to_many_relationships_with_constraints()
    {
        // given: Users with pups of different coats
        $this->buildFixtures();
        $userMapper = Holloway::instance()->getMapper(User::class);

        // when: Count only black pups
        $users = $userMapper->withCount([
            'pups' => function($query) {
                $query->where('coat', 'black');
            }
        ])->get();

        // then: Travis should have 3 black pups (Tobias, Tyler, Tucker)
        $travis = $users->firstWhere('first_name', 'Travis');
        $this->assertEquals(3, $travis->pups_count, 'Travis should have 3 black pups');
    }

    /** @test */
    public function it_counts_belongs_to_many_verifying_pivot_joins()
    {
        // given: Pups with many foods (already set up in fixtures)
        $this->buildFixtures();
        $pupFoodMapper = Holloway::instance()->getMapper(PupFood::class);

        // when: Load pup foods with pups count
        $pupFoods = $pupFoodMapper->withCount('pups')->get();

        // then: Each food should have correct pup count
        $fourHealth = $pupFoods->firstWhere('name', '4Health');
        $tasteOfWild = $pupFoods->firstWhere('name', 'Taste of The Wild');
        $blueBuggalo = $pupFoods->firstWhere('name', 'Blue Buffalo');

        $this->assertEquals(6, $fourHealth->pups_count, '4Health should have 6 pups');
        $this->assertEquals(6, $tasteOfWild->pups_count, 'Taste of The Wild should have 6 pups');
        $this->assertEquals(0, $blueBuggalo->pups_count, 'Blue Buffalo should have 0 pups');
    }

    /** @test */
    public function it_supports_aliasing_with_as_syntax()
    {
        // given: Packs with pups
        $this->buildFixtures();
        $packMapper = Holloway::instance()->getMapper(Pack::class);

        // when: Count pups with custom alias
        $packs = $packMapper->withCount('pups as total_pups')->get();

        // then: Custom alias should appear on entity
        $bennettPack = $packs->firstWhere('name', 'Bennett Pack');
        $this->assertEquals(4, $bennettPack->total_pups, 'Should use custom alias "total_pups"');
        $this->assertObjectNotHasProperty('pups_count', $bennettPack, 'Should not have default "pups_count"');
    }

    /** @test */
    public function it_supports_multiple_counts_with_different_aliases()
    {
        // given: Packs with different types of pups
        $this->buildFixtures();
        $packMapper = Holloway::instance()->getMapper(Pack::class);

        // when: Count black pups and brown pups separately
        $packs = $packMapper->withCount([
            'pups as black_pups' => function($query) {
                $query->where('coat', 'black');
            },
            'pups as brown_pups' => function($query) {
                $query->where('coat', 'brown');
            }
        ])->get();

        // then: Bennett Pack should have separate counts
        $bennettPack = $packs->firstWhere('name', 'Bennett Pack');
        $this->assertEquals(3, $bennettPack->black_pups, 'Should have 3 black pups');
        $this->assertEquals(1, $bennettPack->brown_pups, 'Should have 1 brown pup');
    }

    /** @test */
    public function it_applies_constraints_only_to_count_not_main_query()
    {
        // given: Packs with black and brown pups
        $this->buildFixtures();
        $packMapper = Holloway::instance()->getMapper(Pack::class);

        // when: Count only black pups but fetch all pups
        $packs = $packMapper
            ->withCount([
                'pups as black_pups' => function($query) {
                    $query->where('coat', 'black');
                }
            ])
            ->with('pups')
            ->get();

        // then: Bennett Pack should have 3 black pups counted but 4 pups loaded
        $bennettPack = $packs->firstWhere('name', 'Bennett Pack');
        $this->assertEquals(3, $bennettPack->black_pups, 'Should count only 3 black pups');
        $this->assertCount(4, $bennettPack->pups, 'Should load all 4 pups');
    }

    /** @test */
    public function it_mixes_different_relationship_types_in_one_query()
    {
        // given: Company with collars (hasMany) and collar with pup (belongsTo)
        $this->buildFixtures();
        $companyMapper = Holloway::instance()->getMapper(Company::class);

        // when: Count both hasMany relationships
        $companies = $companyMapper->withCount(['collars', 'pupFoods'])->get();

        // then: Both counts should be correct
        $diamond = $companies->firstWhere('name', 'Diamond Pet Foods and Accessories');
        $this->assertEquals(6, $diamond->collars_count);
        $this->assertEquals(2, $diamond->pup_foods_count);
    }

    /** @test */
    public function it_applies_global_scopes_to_count_queries()
    {
        // given: Collars with SoftDeletes scope
        $this->buildFixtures();
        $collarMapper = Holloway::instance()->getMapper(Collar::class);

        // Soft delete one collar
        \Illuminate\Database\Capsule\Manager::table('collars')
            ->where('id', 1)
            ->update(['deleted_at' => date('Y-m-d H:i:s')]);

        // when: Count pup collars (should exclude soft deleted)
        $pups = Holloway::instance()->getMapper(Pup::class)->withCount('collar')->get();

        // then: Tobias (pup 1) should have 0 collars since his collar was soft deleted
        $tobias = $pups->firstWhere('first_name', 'Tobias');
        $this->assertEquals(0, $tobias->collar_count, 'Should exclude soft deleted collar');

        // Tyler (pup 2) should still have 1 collar
        $tyler = $pups->firstWhere('first_name', 'Tyler');
        $this->assertEquals(1, $tyler->collar_count, 'Should count non-deleted collar');
    }

    /** @test */
    public function it_throws_exception_for_undefined_relationships()
    {
        // given: A mapper
        $this->buildFixtures();
        $packMapper = Holloway::instance()->getMapper(Pack::class);

        // then: Should throw exception for undefined relationship
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Relationship [nonExistentRelation] not defined on mapper');

        // when: Try to count undefined relationship
        $packMapper->withCount('nonExistentRelation')->get();
    }

    /** @test */
    public function it_throws_exception_for_custom_relationships_without_count_support()
    {
        // given: Pack with custom collars relationship (which doesn't support count)
        $this->buildFixtures();
        $packMapper = Holloway::instance()->getMapper(Pack::class);

        // then: Should throw exception
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('does not support withCount()');

        // when: Try to count custom relationship
        $packMapper->withCount('collars')->get();
    }

    /** @test */
    public function it_preserves_relationship_level_scopes_in_count()
    {
        // given: Collars with scope
        $this->buildFixtures();
        $packMapper = Holloway::instance()->getMapper(Pack::class);

        // when: Count pups using a scoped relationship (if we had one)
        // For now, just verify constraints work on the count
        $packs = $packMapper->withCount([
            'pups' => function($query) {
                $query->ofCoat('black');  // Using the scopeOfCoat defined on PupMapper
            }
        ])->get();

        // then: Should only count black pups
        $bennettPack = $packs->firstWhere('name', 'Bennett Pack');
        $this->assertEquals(3, $bennettPack->pups_count, 'Should count only black pups');
    }

    /** @test */
    public function it_counts_belongs_to_many_with_different_pivot_table()
    {
        // given: Users with surrogate pups (different pivot table)
        $this->buildFixtures();
        $userMapper = Holloway::instance()->getMapper(User::class);

        // when: Count surrogate pups
        $users = $userMapper->withCount('surrogatePups')->get();

        // then: Travis should have 2 surrogate pups (Lucky and Duchess)
        $travis = $users->firstWhere('first_name', 'Travis');
        $this->assertEquals(2, $travis->surrogate_pups_count, 'Travis should have 2 surrogate pups');

        // Marilyn should have 2 surrogate pups (Lucky and Duchess)
        $marilyn = $users->firstWhere('first_name', 'Marilyn');
        $this->assertEquals(2, $marilyn->surrogate_pups_count, 'Marilyn should have 2 surrogate pups');
    }

    /** @test */
    public function it_works_with_query_builder_constraints_before_count()
    {
        // given: Packs
        $this->buildFixtures();
        $packMapper = Holloway::instance()->getMapper(Pack::class);

        // when: Apply where clause before withCount
        $packs = $packMapper
            ->where('name', 'Bennett Pack')
            ->withCount('pups')
            ->get();

        // then: Should only return Bennett Pack with count
        $this->assertCount(1, $packs, 'Should only return Bennett Pack');
        $this->assertEquals(4, $packs->first()->pups_count, 'Bennett Pack should have 4 pups');
    }

    /** @test */
    public function it_works_with_query_builder_constraints_after_count()
    {
        // given: Packs
        $this->buildFixtures();
        $packMapper = Holloway::instance()->getMapper(Pack::class);

        // when: Apply withCount then where clause
        $packs = $packMapper
            ->withCount('pups')
            ->where('name', 'Adams Pack')
            ->get();

        // then: Should only return Adams Pack with count
        $this->assertCount(1, $packs, 'Should only return Adams Pack');
        $this->assertEquals(2, $packs->first()->pups_count, 'Adams Pack should have 2 pups');
    }
}
