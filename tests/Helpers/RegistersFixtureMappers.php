<?php

namespace CodeSleeve\Holloway\Tests\Helpers;

use CodeSleeve\Holloway\Holloway;
use CodeSleeve\Holloway\Tests\Fixtures\Mappers\PackMapper;

/**
 * For test classes that need a variation of a fixture mapper in the (static) registry. The regular
 * mappers must be put back in tearDownAfterClass(), since other test classes rely on them.
 */
trait RegistersFixtureMappers
{
    protected static function registerFixtureMappers(string $packMapper = PackMapper::class) : void
    {
        Holloway::instance()->register([
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\CollarMapper',
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\CompanyMapper',
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\PupFoodMapper',
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\PupMapper',
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\UserMapper',
            $packMapper,
        ]);
    }
}
