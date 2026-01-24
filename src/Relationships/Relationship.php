<?php

namespace CodeSleeve\Holloway\Relationships;

use Illuminate\Support\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use stdClass;

interface Relationship
{
    /**
     * Fetch and store the related records for this relationship.
     *
     * @param  Collection $records
     */
    public function load(Collection $records) : void;

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

    /**
     * Build a count subquery for this relationship.
     *
     * @param  string  $parentTable  The parent table name
     * @param  string  $parentKey    The parent's local key column
     * @return \Illuminate\Database\Query\Builder
     */
    public function toCountQuery(string $parentTable, string $parentKey) : QueryBuilder;
}