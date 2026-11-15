<?php

namespace CodeSleeve\Holloway\Tests\Integration;

use CodeSleeve\Holloway\Holloway;
use CodeSleeve\Holloway\Tests\Fixtures\Entities\{Pack, Pup};
use CodeSleeve\Holloway\Tests\Helpers\CanBuildTestFixtures;

class BuilderChunkingTest extends TestCase
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
    public function it_chunks_entities_in_order_even_when_the_query_has_no_order()
    {
        // given
        $this->buildFixtures();
        $chunks = [];

        // when
        $finished = Holloway::instance()->getMapper(Pup::class)->chunk(4, function($pups, $page) use (&$chunks) {
            $chunks[$page] = $pups->map(fn($pup) => $pup->first_name)->all();
        });

        // then
        $this->assertTrue($finished);
        $this->assertSame([1 => ['Tobias', 'Tyler', 'Tucker', 'Trinka'], 2 => ['Lucky', 'Duchess']], $chunks);
    }

    /** @test */
    public function it_stops_chunking_when_the_callback_returns_false()
    {
        // given
        $this->buildFixtures();
        $calls = 0;

        // when
        $finished = Holloway::instance()->getMapper(Pup::class)->chunk(2, function($pups) use (&$calls) {
            $calls++;

            return false;
        });

        // then
        $this->assertFalse($finished);
        $this->assertSame(1, $calls);
    }

    /** @test */
    public function it_chunks_entities_by_their_keys()
    {
        // given
        $this->buildFixtures();
        $chunks = [];

        // when
        $finished = Holloway::instance()->getMapper(Pup::class)->chunkById(4, function($pups) use (&$chunks) {
            $chunks[] = $pups->map(fn($pup) => $pup->id)->all();
        });

        // then
        $this->assertTrue($finished);
        $this->assertSame([[1, 2, 3, 4], [5, 6]], $chunks);
    }

    /** @test */
    public function it_stops_chunking_by_key_when_the_callback_returns_false()
    {
        // given
        $this->buildFixtures();
        $calls = 0;

        // when
        $finished = Holloway::instance()->getMapper(Pack::class)->chunkById(1, function($packs) use (&$calls) {
            $calls++;

            return false;
        });

        // then
        $this->assertFalse($finished);
        $this->assertSame(1, $calls);
    }
}
