<?php

namespace Holloway\Relationships;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Closure;

abstract class HasOneOrMany extends Relationship
{
    /**
     * Load the data for a has one or has many relationship:
     *
     * 1. Use the information on the relationship to fetch the correct table,
     * using the local and foreign key names supplied in the relationship definition.
     *
     * 2. We'll constrian the results using the collection of records that the relationship
     * is being loaded onto.
     *
     * 3. Finally, we'll apply any contraints (if any) that were defined on the load and
     * return the fetched records.
     *
     * @param  Collection $records
     * @param  Closure    $constraints
     * @return Relationship
     */
    public function load(Collection $records, Closure $constraints) : Relationship
    {
        $query = new QueryBuilder($this->connection, $this->connection->getQueryGrammar(), $this->connection->getPostProcessor());

        $this->data = $query->from($this->tableName)
            ->whereIn($this->foreignKeyName, $records->pluck($this->localKeyName)->values()->all())
            ->where($constraints)
            ->get();

        return $this;
    }
}