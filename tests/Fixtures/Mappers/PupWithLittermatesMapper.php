<?php

namespace CodeSleeve\Holloway\Tests\Fixtures\Mappers;

use CodeSleeve\Holloway\Tests\Fixtures\Entities\Pup;

class PupWithLittermatesMapper extends PupMapper
{
    protected array $with = ['littermates'];

    /**
     * A pup has many littermates (the pups in the same pack), which makes its default load lead back to itself.
     */
    public function defineRelations() : void
    {
        parent::defineRelations();

        $this->hasMany('littermates', Pup::class, 'pack_id', 'pack_id');
    }
}
