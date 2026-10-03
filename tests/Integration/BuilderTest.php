<?php

namespace CodeSleeve\Holloway\Tests\Integration;

use CodeSleeve\Holloway\Holloway;
use CodeSleeve\Holloway\Tests\Fixtures\Mappers\PupMapper;

class BuilderTest extends TestCase
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

    /** @test */
    public function it_returns_no_eager_loads_for_a_fresh_query()
    {
        $this->assertSame([], (new PupMapper)->newQuery()->getLoads());
    }

    /** @test */
    public function it_returns_the_relations_that_have_been_set_to_eager_load()
    {
        $builder = (new PupMapper)->with(['collar', 'pack']);

        $this->assertSame(['collar', 'pack'], array_keys($builder->getLoads()));
    }

    /** @test */
    public function it_no_longer_returns_relations_that_have_been_removed_from_the_eager_loads()
    {
        $builder = (new PupMapper)->with(['collar', 'pack'])->without('collar');

        $this->assertSame(['pack'], array_keys($builder->getLoads()));
    }
}
