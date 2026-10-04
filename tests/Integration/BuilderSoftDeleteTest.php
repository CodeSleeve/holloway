<?php

namespace CodeSleeve\Holloway\Tests\Integration;

use CodeSleeve\Holloway\Holloway;
use CodeSleeve\Holloway\Tests\Fixtures\Entities\Pup;
use CodeSleeve\Holloway\Tests\Helpers\CanBuildTestFixtures;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Soft deletes through the query builder, as opposed to the mapper's remove() and restore() methods.
 */
class BuilderSoftDeleteTest extends TestCase
{
    use CanBuildTestFixtures;

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
    public function deleting_through_the_builder_soft_deletes_the_matching_rows()
    {
        // given: one white pup (Lucky) of six
        $this->buildFixtures();
        $pupMapper = Holloway::instance()->getMapper(Pup::class);

        // when
        $pupMapper->where('coat', 'white')->delete();

        // then: the row is still there, with deleted_at set, and no longer returned by normal queries
        $this->assertEquals(6, Capsule::table('pups')->count());
        $this->assertEquals(1, Capsule::table('pups')->whereNotNull('deleted_at')->count());
        $this->assertCount(5, $pupMapper->get());
        $this->assertCount(1, $pupMapper->onlyTrashed()->get());
    }

    /** @test */
    public function the_restore_macro_clears_deleted_at_for_the_matching_rows()
    {
        // given: Lucky is soft deleted
        $this->buildFixtures();
        $pupMapper = Holloway::instance()->getMapper(Pup::class);
        $pupMapper->where('coat', 'white')->delete();

        // when
        $pupMapper->onlyTrashed()->restore();

        // then
        $this->assertEquals(0, Capsule::table('pups')->whereNotNull('deleted_at')->count());
        $this->assertCount(6, $pupMapper->get());
    }

    /** @test */
    public function builder_deletes_and_restores_return_the_number_of_rows_affected()
    {
        // given: two black pups are soft deleted
        $this->buildFixtures();
        $pupMapper = Holloway::instance()->getMapper(Pup::class);

        // when / then
        $this->assertSame(3, $pupMapper->where('coat', 'black')->delete());
        $this->assertSame(3, $pupMapper->onlyTrashed()->restore());
    }

    /** @test */
    public function deleting_through_the_builder_leaves_rows_that_are_already_soft_deleted_alone()
    {
        // given: Lucky was soft deleted a long time ago
        $this->buildFixtures();
        $pupMapper = Holloway::instance()->getMapper(Pup::class);
        Capsule::table('pups')->where('coat', 'white')->update(['deleted_at' => '2000-01-01 00:00:00']);

        // when: everything that is not already deleted is deleted
        $pupMapper->where('id', '>', 0)->delete();

        // then: Lucky's original deleted_at is kept, and everyone else is now deleted
        $this->assertEquals('2000-01-01 00:00:00', Capsule::table('pups')->where('coat', 'white')->value('deleted_at'));
        $this->assertEquals(6, Capsule::table('pups')->whereNotNull('deleted_at')->count());
    }

    /** @test */
    public function force_deleting_through_the_builder_removes_the_rows()
    {
        // given: Lucky (the white pup) has no collar to keep her row referenced
        $this->buildFixtures();
        $pupMapper = Holloway::instance()->getMapper(Pup::class);
        Capsule::table('collars')->where('pup_id', 5)->delete();

        // when
        $pupMapper->withTrashed()->where('coat', 'white')->forceDelete();

        // then
        $this->assertEquals(5, Capsule::table('pups')->count());
    }
}
