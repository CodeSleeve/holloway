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

    /**
     * Return the entity class name for this relationship.
     *
     * @return string|null
     */
    public function getEntityName() : ?string;

    /**
     * Return the raw stdClass related records that have been loaded onto this relationship
     *
     * @return Collection|null
     */
    public function getData() : ?Collection;

    /**
     * @return string
     */
    public function getName() : string;
}