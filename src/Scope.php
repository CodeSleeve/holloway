<?php

namespace Holloway;

interface Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  Builder $builder
     * @param  Mapper  $mapper
     * @return void
     */
    public function apply(Builder $builder, Mapper $mapper);
}
