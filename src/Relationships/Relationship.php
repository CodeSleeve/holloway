<?php

namespace Holloway\Relationships;

use Illuminate\Support\Collection;
use stdClass;
use Closure;

interface Relationship
{
    /**
     * Fetch and store the related records for this relationship.
     *
     * @param  Collection $records
     */
    public function load(Collection $records);

    /**
     * Generate the related entities for a given record.
     *
     * @param  stdClass $record
     * @return mixed
     */
    public function for(stdClass $record);
}