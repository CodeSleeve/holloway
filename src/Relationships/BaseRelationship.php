<?php

namespace CodeSleeve\Holloway\Relationships;

use Illuminate\Support\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use CodeSleeve\Holloway\Builder;
use Closure;
use stdClass;

abstract class BaseRelationship implements Relationship
{
    /**
     * Used to give each self-referential count query a unique table alias.
     */
    protected static int $selfJoinCount = 0;

    protected string $name;
    protected string $table;
    protected string $foreignKeyName;
    protected string $localKeyName;
    protected string $entityName;
    protected Closure $query;
    protected ?Collection $data;

    public function __construct(string $name, string $table, string $foreignKeyName, string $localKeyName, string $entityName, Closure $query)
    {
        $this->name = $name;
        $this->table = $table;
        $this->foreignKeyName = $foreignKeyName;
        $this->localKeyName = $localKeyName;
        $this->entityName = $entityName;
        $this->query = $query;
    }

    /**
     * Fetch and store the related records for this relationship.
     */
    abstract public function load(Collection $records, ?Closure $constraints = null) : void;

    /**
     * Generate the related entities for a given record.
     *
     * @param  stdClass $record
     * @return mixed
     */
    abstract public function for(stdClass $record);

    /**
     * Return the entity class name for this relationship.
     *
     * @return string
     */
    public function getEntityName() : string
    {
        return $this->entityName;
    }

    /**
     * Return the raw stdClass related records that have been loaded onto this relationship
     *
     * @return Collection|null
     */
    public function getData() : ?Collection
    {
        return $this->data;
    }

    /**
     * @return string
     */
    public function getName() : string
    {
        return $this->name;
    }

    /**
     * Get the related mapper's query builder for counting this relationship's related records.
     *
     * When the related table is also the parent table (a tree, or "friends" of the same kind),
     * a count subquery that names the table twice can't tell its own rows from the parent
     * row, so it would correlate the table with itself. As Eloquent does for self relations,
     * the related table is given a unique alias.
     *
     * @param  string  $parentTable
     * @return Builder
     */
    protected function newCountQuery(string $parentTable) : Builder
    {
        $query = ($this->query)();

        if ($this->table === $parentTable) {
            $query->aliasTable('holloway_reserved_' . static::$selfJoinCount++);
        }

        return $query;
    }

    /**
     * Apply the constraints to a count query, and the global scopes, and return the base query.
     *
     * The constraints are applied as a scope (as Eloquent does), so that an "or" in them stays
     * inside the correlation instead of matching rows of other parents.
     *
     * @param  Builder       $query
     * @param  Closure|null  $constraints
     * @return QueryBuilder
     */
    protected function finishCountQuery(Builder $query, ?Closure $constraints) : QueryBuilder
    {
        if ($constraints) {
            $query->callScope($constraints);
        }

        return $query->toBase();
    }
}
