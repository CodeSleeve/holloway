<?php

namespace CodeSleeve\Holloway\Tests\Fixtures\Entities;

/**
 * An entity for a table with custom timestamp column names.
 */
class StampedNote
{
    public ?int $id = null;
    public ?string $date_created = null;
    public ?string $date_modified = null;

    public function __construct(public string $name)
    {
        //
    }
}
