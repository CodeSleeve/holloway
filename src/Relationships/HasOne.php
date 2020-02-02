<?php

namespace CodeSleeve\Holloway\Relationships;

use stdClass;

class HasOne extends HasOneOrMany
{
    /**
     * Generate the related related entities for a given record.
     *
     * 1. Filter the loaded data for this relationship to include the record that should be
     * loaded onto the related record.
     *
     * 2. Map that records into an entity instance.
     *
     * 3. Since this is a HasOne relationship, we'll return the first record from the mapped results.
     *
     * @param  stdClass $record
     * @return stdClass|null
     */
    public function for(stdClass $record) : ?stdClass
    {
        return $this->data
            ->filter(fn(stdClass $relatedRecord) => $relatedRecord->{$this->foreignKeyName} == $record->{$this->localKeyName})
            ->first();
    }
}