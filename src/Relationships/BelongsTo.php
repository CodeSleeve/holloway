<?php

namespace CodeSleeve\Holloway\Relationships;

use Illuminate\Support\Collection;
use Closure;
use stdClass;

class BelongsTo extends BaseRelationship
{
    /**
     * Load the data for a belongs to relationship:
     *
     * 1. Use the information on the relationship to fetch the correct table,
     * using the local and foreign key names supplied in the relationship definition.
     *
     * 2. We'll constrian the results using the collection of records that the relationship
     * is being loaded onto.
     *
     * 3. Finally, we'll apply any contraints (if any) that were defined on the load and
     * return the fetched records.
     */
    public function load(Collection $records, ?Closure $constraints = null) : void
    {
        $constraints = $constraints ?: function() {};

        $this->data = ($this->query)()
            ->where($constraints) // Allow for constraints to be applied to the to a Holloway\Builder $query
            ->toBase()
            ->from($this->table)
            ->whereIn("{$this->table}.{$this->localKeyName}", $records->pluck($this->foreignKeyName)->values()->all())
            ->get();
    }

    /**
     * Generate the related related entities for a given record.
     *
     * 1. Filter the loaded data for this relationship to include the record that should be
     * loaded onto the related record.
     *
     * 2. Map that records into an entity instance.
     *
     * 3. Since this is a BelongsTo relationship, we'll return the first record from the mapped results.
     *
     * @param  stdClass $record
     * @return stdClass|null
     */
    public function for(stdClass $record) : ?stdClass
    {
        return $this->data
            ->filter(function(stdClass $relatedRecord) use ($record) {
                return $relatedRecord->{$this->localKeyName} == $record->{$this->foreignKeyName};
            })
            ->first();
    }
}