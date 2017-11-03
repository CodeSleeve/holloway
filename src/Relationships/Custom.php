<?php

namespace Holloway\Relationships;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use stdClass;
use Closure;

class Custom implements Relationship
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var Closure
     */
    protected $loadData;

    /**
     * @var Closure
     */
    protected $parseData;

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
     * @param Closure      $loadData
     * @param Closure      $parseData
     * @param QueryBuilder $query
     */
    public function __construct(string $name, Closure $loadData, Closure $parseData, QueryBuilder $query)
    {
        $this->loadData = $loadData;
        $this->parseData = $parseData;
        $this->query = $query;
    }

    /**
     * Fetch and store the related records for this relationship.
     *
     * @param  Collection $records
     */
    public function load(Collection $records)
    {
        $loadData = $this->loadData;

        $this->data = $loadData($this->query->newQuery(), $records);
    }

    /**
     * Generate the related entities for a given record.
     *
     * @param  stdClass $record
     * @return mixed
     */
    public function for(stdClass $record)
    {
        $parseData = $this->parseData;

        return $parseData($record, $this->data);
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
}