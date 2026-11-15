<?php

namespace CodeSleeve\Holloway\Tests\Fixtures\Mappers;

use CodeSleeve\Holloway\Mapper;
use CodeSleeve\Holloway\Tests\Fixtures\Entities\Pup;
use Illuminate\Support\Collection;

/**
 * A mapper that overrides query methods with its own return types. Used to check that the
 * PHPStan extension leaves overridden methods alone.
 */
abstract class OverridingMapper extends Mapper
{
    protected string $entityClassName = Pup::class;

    /**
     * @return Collection<int, string>
     */
    public function get() : Collection
    {
        return new Collection(['a']);
    }

    public function first() : ?\stdClass
    {
        return null;
    }

    /**
     * @param  mixed  $id
     * @return array<string, mixed>|null
     */
    public function find($id) : ?array
    {
        return null;
    }
}
