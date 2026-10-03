<?php

namespace CodeSleeve\Holloway\Tests\PhpStan\Data\MapperUnions;

use CodeSleeve\Holloway\Tests\Fixtures\Entities\Collar;
use CodeSleeve\Holloway\Tests\Fixtures\Entities\Pup;
use CodeSleeve\Holloway\Tests\Fixtures\Mappers\CollarMapper;
use CodeSleeve\Holloway\Tests\Fixtures\Mappers\PupMapper;

use function PHPStan\Testing\assertType;

function unionOfMappers(PupMapper|CollarMapper $mapper) : void
{
    // There is no single entity type for a union, so it must not be resolved to just the first mapper's.
    assertType('Illuminate\Support\Collection<int, mixed>', $mapper->get());
    assertType('mixed', $mapper->first());
}

function unionOfMappersKeepsScopeTypes(PupMapper|CollarMapper $mapper) : void
{
    // Soft-delete macros are resolved per mapper, so the result is the union of both builders.
    assertType(
        'CodeSleeve\Holloway\Builder<' . Collar::class . ', ' . CollarMapper::class . '>|CodeSleeve\Holloway\Builder<' . Pup::class . ', ' . PupMapper::class . '>',
        $mapper->withTrashed()
    );
}
