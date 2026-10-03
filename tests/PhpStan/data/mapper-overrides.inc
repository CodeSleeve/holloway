<?php

namespace CodeSleeve\Holloway\Tests\PhpStan\Data\MapperOverrides;

use CodeSleeve\Holloway\Tests\Fixtures\Entities\Pup;
use CodeSleeve\Holloway\Tests\Fixtures\Mappers\OverridingMapper;

use function PHPStan\Testing\assertType;

function overriddenMethodsKeepTheirOwnReturnTypes(OverridingMapper $mapper) : void
{
    assertType('Illuminate\Support\Collection<int, string>', $mapper->get());
    assertType('stdClass|null', $mapper->first());
    assertType('array<string, mixed>|null', $mapper->find(1));

    // Methods that are not overridden still resolve to the entity.
    assertType('Illuminate\Support\Collection<int, ' . Pup::class . '>', $mapper->all());
}
