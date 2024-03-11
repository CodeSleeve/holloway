<?php

namespace CodeSleeve\Holloway\Relationships;

use Illuminate\Support\Collection;
use Closure;
use stdClass;

abstract class BaseRelationship implements Relationship
{
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
}