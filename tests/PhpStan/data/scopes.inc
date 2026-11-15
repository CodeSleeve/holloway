<?php

namespace CodeSleeve\Holloway\Tests\PhpStan\Data\Scopes;

use CodeSleeve\Holloway\Tests\Fixtures\Entities\Collar;
use CodeSleeve\Holloway\Tests\Fixtures\Entities\Pup;
use CodeSleeve\Holloway\Tests\Fixtures\Mappers\CollarMapper;
use CodeSleeve\Holloway\Tests\Fixtures\Mappers\PupMapper;

use function PHPStan\Testing\assertType;

function localScopes(PupMapper $pups, CollarMapper $collars) : void
{
    // A scope is called without its "scope" prefix, on the mapper...
    assertType('CodeSleeve\Holloway\Builder<' . Pup::class . ', ' . PupMapper::class . '>', $pups->ofCoat('black'));

    // ...or anywhere in a builder chain.
    assertType('CodeSleeve\Holloway\Builder<' . Pup::class . ', ' . PupMapper::class . '>', $pups->where('a', 'b')->ofCoat('white'));
    assertType('Illuminate\Support\Collection<int, ' . Pup::class . '>', $pups->ofCoat('black')->orderBy('name')->get());
    assertType(Pup::class . '|null', $pups->ofCoat('black')->first());

    // Scopes without arguments.
    assertType('Illuminate\Support\Collection<int, ' . Collar::class . '>', $collars->thatAreOrange()->get());
}

function softDeleteMacros(PupMapper $pups) : void
{
    assertType('Illuminate\Support\Collection<int, ' . Pup::class . '>', $pups->withTrashed()->get());
    assertType('Illuminate\Support\Collection<int, ' . Pup::class . '>', $pups->where('a', 'b')->onlyTrashed()->get());
    assertType('CodeSleeve\Holloway\Builder<' . Pup::class . ', ' . PupMapper::class . '>', $pups->newQuery()->withoutTrashed());
}
