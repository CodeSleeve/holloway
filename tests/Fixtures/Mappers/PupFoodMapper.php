<?php

namespace CodeSleeve\Holloway\Tests\Fixtures\Mappers;

use CodeSleeve\Holloway\Tests\Fixtures\Entities\{Company, Pup, PupFood};

class PupFoodMapper extends Mapper
{
    protected string $table = 'pup_foods';
    protected string $entityClassName = PupFood::class;

    /**
     * @return  void
     */
    public function defineRelations() : void
    {
        $this->belongsTo('company', Company::class);    // A pup food belongs to a company (NOTE: For testing purposes, we've intentionally left the table name, local key name, and foreign key name parameters null).
        $this->belongsToMany('pups', Pup::class);       // A pup food belongs to many pups (NOTE: For testing purposes, we've intentionally left the table name, local key name, and foreign key name parameters null).
    }
}