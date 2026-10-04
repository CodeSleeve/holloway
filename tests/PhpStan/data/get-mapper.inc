<?php

namespace CodeSleeve\Holloway\Tests\PhpStan\Data\GetMapper;

use CodeSleeve\Holloway\Holloway;
use CodeSleeve\Holloway\Tests\Fixtures\Entities\Pup;
use CodeSleeve\Holloway\Tests\Fixtures\Entities\Collar;

use function PHPStan\Testing\assertType;

/**
 * @param class-string<Pup> $class
 */
function getMapper(Holloway $holloway, Pup $pup, string $name, string $class) : void
{
    assertType('CodeSleeve\Holloway\Mapper<' . Pup::class . '>', $holloway->getMapper(Pup::class));
    assertType('CodeSleeve\Holloway\Mapper<' . Pup::class . '>', $holloway->getMapper($pup));
    assertType('CodeSleeve\Holloway\Mapper<' . Pup::class . '>', $holloway->getMapper($class));
    assertType('CodeSleeve\Holloway\Mapper', $holloway->getMapper($name));

    // The type chain holds through the registry.
    assertType(Pup::class . '|null', $holloway->getMapper(Pup::class)->find(1));
    assertType('Illuminate\Support\Collection<int, ' . Pup::class . '>', $holloway->getMapper(Pup::class)->get());
    assertType('CodeSleeve\Holloway\Builder<' . Pup::class . ', CodeSleeve\Holloway\Mapper<' . Pup::class . '>>', $holloway->getMapper(Pup::class)->where('a', 'b'));
}

function getMapperUnion(Holloway $holloway, bool $flag) : void
{
    $class = $flag ? Pup::class : Collar::class;
    assertType('CodeSleeve\Holloway\Mapper<' . Collar::class . '|' . Pup::class . '>', $holloway->getMapper($class));
}
