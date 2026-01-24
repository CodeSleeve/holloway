<?php

namespace CodeSleeve\Holloway\Relationships;

use Closure;
use BadMethodCallException;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use stdClass;

class Custom implements Relationship
{
    protected string $name;
    protected Closure $load;
    protected Closure $for;
    protected ?Closure $map;
    protected ?string $entityName = null;
    protected bool $shouldLimitToOne;
    protected Closure $query;
    protected ?Closure $count = null;
    protected ?Collection $data;

    /**
     * @param string       $name
     * @param Closure      $load
     * @param Closure      $for
     * @param mixed        $mapOrEntityName
     * @param bool         $shouldLimitToOne
     * @param Closure      $query
     * @param Closure|null $count
     */
    public function __construct(string $name, Closure $load, Closure $for, $mapOrEntityName, bool $shouldLimitToOne, Closure $query, ?Closure $count = null)
    {
        $this->name = $name;
        $this->load = $load;
        $this->for = $for;

        if (is_string($mapOrEntityName) && $mapOrEntityName !== '') {
            $this->entityName = $mapOrEntityName;
        } else if ($mapOrEntityName instanceof Closure) {
            $this->map = $mapOrEntityName;
        } else {
            throw new \InvalidArgumentException('A custom relationship must contain either a Closure for mapping results or the entity class name of the mapper to be used.');
        }

        $this->shouldLimitToOne = $shouldLimitToOne;
        $this->query = $query;
        $this->count = $count;
    }

    /**
     * Fetch and store the related records for this relationship.
     *
     * @param  Collection $records
     */
    public function load(Collection $records) : void
    {
        $load = $this->load;

        $this->data = $load(($this->query)()->toBase(), $records);  // Convert the query to a base query instance
    }

    /**
     * Generate the related entities for a given record.
     *
     * @param  stdClass $record
     * @return mixed
     */
    public function for(stdClass $record)
    {
        $for = $this->for;

        return $this->data->filter(fn($relatedRecord) => $for($record, $relatedRecord))->values();
    }

    /**
     * Return the entity class name for this relationship.
     *
     * @return string|null
     */
    public function getEntityName() : ?string
    {
        return $this->entityName;
    }

    /**
     * @return Closure|null
     */
    public function getMap() : ?Closure
    {
        return $this->map;
    }

    /**
     * @return bool
     */
    public function shouldLimitToOne() : bool
    {
        return $this->shouldLimitToOne;
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
     * Build a count subquery for Custom relationships.
     * Custom relationships must provide a count closure in their constructor to support withCount().
     *
     * @param  string  $parentTable
     * @param  string  $parentKey
     * @return \Illuminate\Database\Query\Builder
     * @throws \BadMethodCallException
     */
    public function toCountQuery(string $parentTable, string $parentKey) : QueryBuilder
    {
        if (!$this->count) {
            throw new BadMethodCallException(
                "Custom relationship [{$this->name}] does not support withCount(). " .
                "To add count support, provide a count closure as the 7th parameter to the Custom relationship constructor. " .
                "The closure should accept (QueryBuilder \$query, string \$parentTable, string \$parentKey) and return a QueryBuilder with count logic."
            );
        }

        $query = ($this->query)();

        // Convert to base query builder if needed
        if ($query instanceof \CodeSleeve\Holloway\Builder) {
            $query = $query->applyScopes()->toBase();
        } else {
            $query = $query->toBase();
        }

        // Let the count closure configure the query
        return ($this->count)($query, $parentTable, $parentKey);
    }
}