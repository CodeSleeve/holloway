<?php

namespace Holloway\Relationships;

use Illuminate\Support\Collection;
use stdClass;

class HasMany extends HasOneOrMany
{
    /**
     * Generate the related related entities for a given record.
     *
     * 1. Filter the loaded data for this relationship to include the record that should be
     * loaded onto the related record.
     *
     * 2. Map that records into an entity instance.
     *
     * 3. Since this is a HasMany relationship, we'll return all records from the mapped results.
     *
     * @param  stdClass $record
     * @return Collection
     */
    public function for(stdClass $record) : Collection
    {
        return $this->data
            ->filter(function(stdClass $relatedRecord) use ($record) {
                return $relatedRecord->{$this->foreignKeyName} == $record->{$this->localKeyName};
            });
    }
}