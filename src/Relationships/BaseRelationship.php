<?php

namespace CodeSleeve\Holloway\Relationships;

use Illuminate\Support\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Closure;
use stdClass;

abstract class BaseRelationship implements Relationship
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $tableName;

    /**
     * @var string
     */
    protected $foreignKeyName;

    /**
     * @var string
     */
    protected $localKeyName;

    /**
     *  @var string
     */
    protected $entityName;

    /**
     * @var QueryBuilder
     */
    protected $query;

    /**
     * @var Collection|null
     */
    protected $data;

    /**
     * @param string       $name
     * @param string       $tableName
     * @param string       $foreignKeyName
     * @param string       $localKeyName
     * @param string       $entityName
     * @param QueryBuilder $query
     */
    public function __construct(string $name, string $tableName, string $foreignKeyName, string $localKeyName, string $entityName, QueryBuilder $query)
    {
        $this->name = $name;
        $this->tableName = $tableName;
        $this->foreignKeyName = $foreignKeyName;
        $this->localKeyName = $localKeyName;
        $this->entityName = $entityName;
        $this->query = $query;
    }

    /**
     * Fetch and store the related records for this relationship.
     *
     * @param  Collection    $records
     * @param  Closure|null  $constraints
     */
    abstract public function load(Collection $records, ?Closure $constraints = null);

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