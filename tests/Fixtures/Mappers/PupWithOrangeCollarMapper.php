<?php

namespace CodeSleeve\Holloway\Tests\Fixtures\Mappers;

class PupWithOrangeCollarMapper extends PupMapper
{
    /**
     * A default load with constraints (which can't be written in the $with property).
     */
    public function getWith() : array
    {
        return ['collar' => fn($query) => $query->where('color', 'orange')];
    }
}