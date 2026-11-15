<?php

namespace CodeSleeve\Holloway\Relationships;

use Closure;
use Illuminate\Support\Collection;
use stdClass;

class Custom implements Relationship
{
    protected string $name;
    protected Closure $load;
    protected Closure $for;
    protected ?Closure $map = null;
    protected ?string $entityName = null;
    protected bool $shouldLimitToOne;
    protected Closure $query;
    protected ?Collection $data = null;

    public function __construct(string $name, Closure $load, Closure $for, $mapOrEntityName, bool $shouldLimitToOne, Closure $query)
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
     * Fetch and store the related records for this relationship.
     *
     * The constraints (if any were given) are handed to the load closure as a third argument, since only
     * it knows how the records are queried. A load closure that doesn't take a third argument ignores them.
     *
     * @param  Collection   $records
     * @param  Closure|null $constraints
     */
    public function load(Collection $records, ?Closure $constraints = null) : void
    {
        $load = $this->load;
        $constraints = $constraints ?: function() {};

        $this->data = $load(($this->query)()->toBase(), $records, $constraints);  // Convert the query to a base query instance
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
}