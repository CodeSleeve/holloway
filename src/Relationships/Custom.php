<?php

namespace CodeSleeve\Holloway\Relationships;

use Closure;
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
    protected QueryBuilder $query;
    protected ?Collection $data;

    /**
     * @param string       $name
     * @param Closure      $load
     * @param Closure      $for
     * @param mixed        $mapOrEntityName
     * @param bool         $shouldLimitToOne
     * @param QueryBuilder $query
     */
    public function __construct(string $name, Closure $load, Closure $for, $mapOrEntityName, bool $shouldLimitToOne, QueryBuilder $query)
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
    }

    /**
     * @return void
     */
    public function __clone()
    {
        $this->query = clone $this->query;
    }

    /**
     * Fetch and store the related records for this relationship.
     *
     * @param  Collection $records
     */
    public function load(Collection $records)
    {
        $load = $this->load;

        $this->data = $load($this->query, $records);
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

        return $this->data->filter(fn($relatedRecord) => $for($record, $relatedRecord));
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
}