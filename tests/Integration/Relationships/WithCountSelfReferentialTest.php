<?php

namespace CodeSleeve\Holloway\Tests\Integration\Relationships;

use CodeSleeve\Holloway\Holloway;
use CodeSleeve\Holloway\SoftDeletingScope;
use CodeSleeve\Holloway\Tests\Fixtures\Entities\Category;
use CodeSleeve\Holloway\Tests\Helpers\CanBuildTestFixtures;
use CodeSleeve\Holloway\Tests\Integration\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * withCount() on relationships whose related table is also the parent table.
 *
 * Tree:   1 Root A ─┬─ 2 A1 ── 5 A1a
 *                   ├─ 3 A2
 *                   └─ 4 A3 (soft deleted)
 *         6 Root B ── 7 B1
 *
 * Related (categories_related): 1 → 2, 3, 4(deleted);  2 → 1;  6 → 7
 */
class WithCountSelfReferentialTest extends TestCase
{
    use CanBuildTestFixtures;

    public static function setUpBeforeClass(): void
    {
        Holloway::instance()->register([
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\CategoryMapper',
        ]);
    }

    private function buildCategories() : void
    {
        Capsule::table('categories')->insert([
            ['id' => 1, 'parent_id' => null, 'name' => 'Root A', 'deleted_at' => null],
            ['id' => 2, 'parent_id' => 1,    'name' => 'A1',     'deleted_at' => null],
            ['id' => 3, 'parent_id' => 1,    'name' => 'A2',     'deleted_at' => null],
            ['id' => 4, 'parent_id' => 1,    'name' => 'A3',     'deleted_at' => '2026-01-01 00:00:00'],
            ['id' => 5, 'parent_id' => 2,    'name' => 'A1a',    'deleted_at' => null],
            ['id' => 6, 'parent_id' => null, 'name' => 'Root B', 'deleted_at' => null],
            ['id' => 7, 'parent_id' => 6,    'name' => 'B1',     'deleted_at' => null],
        ]);

        Capsule::table('categories_related')->insert([
            ['category_id' => 1, 'related_category_id' => 2],
            ['category_id' => 1, 'related_category_id' => 3],
            ['category_id' => 1, 'related_category_id' => 4],
            ['category_id' => 2, 'related_category_id' => 1],
            ['category_id' => 6, 'related_category_id' => 7],
        ]);
    }

    /** @return array<int, int> category id => count */
    private function counts(string $attribute, $builder) : array
    {
        $counts = [];

        foreach ($builder->orderBy('id')->get() as $category) {
            $counts[$category->id] = (int) $category->$attribute;
        }

        return $counts;
    }

    /** @test */
    public function it_counts_a_self_referential_has_many_relationship_per_parent_row()
    {
        $this->buildCategories();
        $mapper = Holloway::instance()->getMapper(Category::class);

        $counts = $this->counts('children_count', $mapper->withCount('children'));

        // Root A has A1 and A2 (A3 is soft deleted and must not be counted).
        $this->assertSame([1 => 2, 2 => 1, 3 => 0, 5 => 0, 6 => 1, 7 => 0], $counts);
    }

    /** @test */
    public function it_counts_a_self_referential_belongs_to_relationship_per_parent_row()
    {
        $this->buildCategories();
        $mapper = Holloway::instance()->getMapper(Category::class);

        $counts = $this->counts('parent_count', $mapper->withCount('parent'));

        $this->assertSame([1 => 0, 2 => 1, 3 => 1, 5 => 1, 6 => 0, 7 => 1], $counts);
    }

    /** @test */
    public function it_counts_a_self_referential_belongs_to_many_relationship_per_parent_row()
    {
        $this->buildCategories();
        $mapper = Holloway::instance()->getMapper(Category::class);

        $counts = $this->counts('related_count', $mapper->withCount('related'));

        // Root A is related to A1 and A2 (A3 is soft deleted and must not be counted).
        $this->assertSame([1 => 2, 2 => 1, 3 => 0, 5 => 0, 6 => 1, 7 => 0], $counts);
    }

    /** @test */
    public function it_applies_constraints_to_a_self_referential_count()
    {
        $this->buildCategories();
        $mapper = Holloway::instance()->getMapper(Category::class);

        $counts = $this->counts('children_count', $mapper->withCount([
            'children' => fn($query) => $query->where('name', 'like', 'A1%'),
        ]));

        // Only A1 (child of Root A) and A1a (child of A1) match the constraint.
        $this->assertSame([1 => 1, 2 => 1, 3 => 0, 5 => 0, 6 => 0, 7 => 0], $counts);
    }

    /** @test */
    public function an_or_constraint_stays_inside_the_correlation_of_a_self_referential_count()
    {
        $this->buildCategories();
        $mapper = Holloway::instance()->getMapper(Category::class);

        $counts = $this->counts('children_count', $mapper->withCount([
            'children' => fn($query) => $query->where('name', 'A1')->orWhere('name', 'B1'),
        ]));

        // A1 is a child of Root A and B1 is a child of Root B; neither counts for anyone else.
        $this->assertSame([1 => 1, 2 => 0, 3 => 0, 5 => 0, 6 => 1, 7 => 0], $counts);
    }

    /** @test */
    public function it_supports_aliases_and_several_counts_on_the_same_self_referential_relationship()
    {
        $this->buildCategories();
        $mapper = Holloway::instance()->getMapper(Category::class);

        $categories = $mapper->withCount([
            'children as kids',
            'children as a_kids' => fn($query) => $query->where('name', 'like', 'A%'),
            'related',
        ])->where('id', 1)->get();

        $this->assertEquals(2, $categories[0]->kids);
        $this->assertEquals(2, $categories[0]->a_kids);
        $this->assertEquals(2, $categories[0]->related_count);
    }

    /** @test */
    public function the_count_matches_what_eager_loading_loads()
    {
        $this->buildCategories();
        $mapper = Holloway::instance()->getMapper(Category::class);

        foreach ($mapper->with(['children', 'related'])->withCount(['children', 'related'])->orderBy('id')->get() as $category) {
            $this->assertCount((int) $category->children_count, $category->children, "children of category {$category->id}");
            $this->assertCount((int) $category->related_count, $category->related, "related of category {$category->id}");
        }
    }

    /** @test */
    public function a_constraint_can_remove_a_global_scope_from_a_self_referential_count()
    {
        $this->buildCategories();
        $mapper = Holloway::instance()->getMapper(Category::class);

        $counts = $this->counts('children_count', $mapper->withCount([
            'children' => fn($query) => $query->withoutGlobalScope(SoftDeletingScope::class),
        ]));

        // Root A's soft deleted child (A3) is counted now.
        $this->assertSame([1 => 3, 2 => 1, 3 => 0, 5 => 0, 6 => 1, 7 => 0], $counts);
    }

    /** @test */
    public function the_global_scopes_are_applied_to_a_self_referential_count_once_and_to_its_alias()
    {
        $mapper = Holloway::instance()->getMapper(Category::class);

        $sql = $mapper->withCount('children')->toSql();

        // Once for the parent query, and once for the count subquery (on the alias).
        $this->assertSame(2, substr_count($sql, 'deleted_at'));
        $this->assertMatchesRegularExpression('/holloway_reserved_\d+\W+deleted_at/', $sql);
    }
}
