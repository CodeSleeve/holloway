<?php

namespace CodeSleeve\Holloway\Tests\PhpStan\Data\Mapper;

use CodeSleeve\Holloway\Tests\Fixtures\Entities\Pup;
use CodeSleeve\Holloway\Tests\Fixtures\Mappers\PupMapper;

use function PHPStan\Testing\assertType;

/**
 * @param array<int, int> $ids
 */
function concreteMapper(PupMapper $mapper, mixed $id, array $ids, int $int) : void
{
    // Single entity results
    assertType(Pup::class . '|null', $mapper->find(1));
    assertType(Pup::class . '|null', $mapper->find($int));
    assertType('Illuminate\Support\Collection<int, ' . Pup::class . '>', $mapper->find($ids));
    assertType(Pup::class . '|Illuminate\Support\Collection<int, ' . Pup::class . '>|null', $mapper->find($id));
    assertType(Pup::class . '|null', $mapper->first());
    assertType(Pup::class, $mapper->findOrFail(1));
    assertType(Pup::class, $mapper->firstOrFail());
    assertType(Pup::class, $mapper->makeEntity(new \stdClass));

    // Collections
    assertType('Illuminate\Support\Collection<int, ' . Pup::class . '>', $mapper->get());
    assertType('Illuminate\Support\Collection<int, ' . Pup::class . '>', $mapper->all());
    assertType('Illuminate\Support\Collection<int, ' . Pup::class . '>', $mapper->findMany($ids));
    assertType('Illuminate\Support\Collection<int, ' . Pup::class . '>', $mapper->newCollection());

    // Paginators
    assertType('Illuminate\Contracts\Pagination\LengthAwarePaginator<int, ' . Pup::class . '>', $mapper->paginate());
    assertType('Illuminate\Contracts\Pagination\Paginator<int, ' . Pup::class . '>', $mapper->simplePaginate());

    // Builders and chains
    assertType('CodeSleeve\Holloway\Builder<' . Pup::class . '>', $mapper->newQuery());
    assertType('CodeSleeve\Holloway\Builder<' . Pup::class . '>', $mapper->newQueryWithoutScopes());
    assertType('CodeSleeve\Holloway\Builder<' . Pup::class . '>', $mapper->with('collar'));
    assertType('CodeSleeve\Holloway\Builder<' . Pup::class . '>', $mapper->withCount('collar'));
    assertType('Illuminate\Support\Collection<int, ' . Pup::class . '>', $mapper->where('a', '=', 'b')->orderBy('name')->get());
    assertType(Pup::class . '|null', $mapper->with('collar')->where('a', 'b')->groupBy('a')->first());
}

/**
 * Query-builder arguments that Illuminate accepts must not be flagged by the
 * forwarded method signatures (check by running PHPStan over this file at level 8).
 */
function permissiveArguments(PupMapper $mapper) : void
{
    assertType('CodeSleeve\Holloway\Builder<' . Pup::class . '>', $mapper->orderBy(new \Illuminate\Database\Query\Expression('name')));
    assertType('CodeSleeve\Holloway\Builder<' . Pup::class . '>', $mapper->where('a', 'b')->groupBy(['a', 'b']));
    assertType('CodeSleeve\Holloway\Builder<' . Pup::class . '>', $mapper->where('a', 'b')->having('c', '>', 5));
    assertType('CodeSleeve\Holloway\Builder<' . Pup::class . '>', $mapper->whereBetween('a', new \ArrayObject([1, 2])));
}
