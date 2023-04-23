<?php

namespace CodeSleeve\Holloway\Tests\Fixtures\Mappers;

use CodeSleeve\Holloway\Tests\Fixtures\Entities\{Collar, Company, Pup};

class CollarMapper extends Mapper
{
    protected string $table = 'collars';
    protected string $entityClassName = Collar::class;

    /**
     * @return  void
     */
    public function defineRelations()
    {
        $this->belongsTo('company', Company::class, 'company_id', 'id');    // A collar is manufactured by a company.
        $this->belongsTo('pup', Pup::class, 'pup_id', 'id');                // A collar belongs to a pup.
    }

    /**
     * Scope a query to only include orange collars.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeThatAreOrange($query)
    {
        return $query->where('color', 'orange');
    }
}