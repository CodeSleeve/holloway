<?php

namespace CodeSleeve\Holloway;

interface Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Mapper $mapper) : void;
}
