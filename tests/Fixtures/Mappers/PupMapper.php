<?php

namespace CodeSleeve\Holloway\Tests\Fixtures\Mappers;

use CodeSleeve\Holloway\SoftDeletes;
use CodeSleeve\Holloway\Tests\Fixtures\Entities\{Pup, Collar, Pack, PupFood};

class PupMapper extends Mapper
{
    use SoftDeletes;

    protected string $table = 'pups';
    protected string $entityClassName = Pup::class;

    /**
     * @return  void
     */
    public function defineRelations()
    {
        $this->hasOne('collar', Collar::class, 'pup_id', 'id');    // A pup has one collar.
        $this->belongsTo('pack', Pack::class, 'pack_id', 'id');    // A pup belongs to a pack.
        $this->belongsToMany('pupFoods', PupFood::class);          // A pup belongs to many foods (NOTE: For testing purposes, we've intentionally left the table name, local key name, and foreign key name parameters null).
    }

    /**
     * @return string
     */
    public function getEntityClassName() : string
    {
        return $this->entityClassName;
    }

    /**
     * Scope a query to only include pups of a given coat color.
     *
     * @param Builder $query
     * @param string  $coat
     * @return Builder
     */
    public function scopeOfCoat($query, string $coat)
    {
        return $query->where('coat', $coat);
    }
}