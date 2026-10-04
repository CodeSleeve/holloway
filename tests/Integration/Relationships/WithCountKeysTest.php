<?php

namespace CodeSleeve\Holloway\Tests\Integration\Relationships;

use CodeSleeve\Holloway\Holloway;
use CodeSleeve\Holloway\Tests\Fixtures\Entities\{Pack, Pup};
use CodeSleeve\Holloway\Tests\Fixtures\Mappers\PackMapper;
use CodeSleeve\Holloway\Tests\Helpers\{CanBuildTestFixtures, RegistersFixtureMappers};
use CodeSleeve\Holloway\Tests\Integration\TestCase;

/**
 * PackMapper declares hasMany('pups', Pup::class, 'pack_id', 'id'), so the local key is `id`.
 * This mapper gives the packs table a different primary key name, so the relationship's local key
 * and the mapper's key are no longer the same column.
 */
class NameKeyedPackMapper extends PackMapper
{
    protected string $primaryKey = 'name';
}

class WithCountKeysTest extends TestCase
{
    use CanBuildTestFixtures, RegistersFixtureMappers;

    public static function setUpBeforeClass(): void
    {
        self::registerFixtureMappers(NameKeyedPackMapper::class);
    }

    public static function tearDownAfterClass(): void
    {
        self::registerFixtureMappers();
    }

    /** @test */
    public function it_counts_has_many_relationships_using_the_relationships_local_key_and_not_the_mappers_key()
    {
        // given: the relationship's local key (id) differs from the mapper's primary key (name)
        $this->buildFixtures();
        $packMapper = Holloway::instance()->getMapper(Pack::class);

        // when
        $packs = $packMapper->withCount('pups')->orderBy('id')->get();

        // then: it counts the same pups that eager loading would load
        $this->assertCount(2, $packs);
        $this->assertEquals(4, $packs[0]->pups_count);   // Bennett Pack
        $this->assertEquals(2, $packs[1]->pups_count);   // Adams Pack
    }

    /** @test */
    public function the_count_matches_what_eager_loading_loads_for_the_same_relationship()
    {
        // given
        $this->buildFixtures();
        $packMapper = Holloway::instance()->getMapper(Pack::class);

        // when
        $loaded = $packMapper->with('pups')->where('id', 1)->first();
        $counted = $packMapper->withCount('pups')->where('id', 1)->first();

        // then
        $this->assertCount(4, $loaded->pups);
        $this->assertEquals(count($loaded->pups), $counted->pups_count);
    }
}
