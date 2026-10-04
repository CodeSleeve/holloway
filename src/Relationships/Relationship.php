<?php

namespace CodeSleeve\Holloway\Relationships;

use Closure;
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
     * Build a count subquery for this relationship, correlated with the parent table.
     *
     * The built-in relationships correlate on their own keys and apply the related mapper's
     * global scopes after the constraints (if any), which are handed the related mapper's query
     * builder. $parentKey is the parent mapper's primary key, for relationships (such as custom
     * ones) that correlate on it, and a custom relationship's count closure is responsible for
     * its own scopes.
     *
     * @param  string        $parentTable   The parent table name
     * @param  string        $parentKey     The parent mapper's primary key
     * @param  Closure|null  $constraints   Extra constraints, applied to the query builder as a scope
     * @return \Illuminate\Database\Query\Builder
     */
    public function toCountQuery(string $parentTable, string $parentKey, ?Closure $constraints = null) : QueryBuilder;
}