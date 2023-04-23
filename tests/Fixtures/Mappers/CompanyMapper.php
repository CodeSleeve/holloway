<?php

namespace CodeSleeve\Holloway\Tests\Fixtures\Mappers;

use CodeSleeve\Holloway\Tests\Fixtures\Entities\{Collar, Company, PupFood};

class CompanyMapper extends Mapper
{
    protected string $table = 'companies';
    protected string $entityClassName = Company::class;

    /**
     * @return  void
     */
    public function defineRelations()
    {
        $this->hasMany('collars', Collar::class);     // A company has many collars (NOTE: For testing purposes, we've intentionally left the table name, local key name, and foreign key name parameters null).
        $this->hasMany('pupFoods', PupFood::class);   // A company has many pup foods (NOTE: For testing purposes, we've intentionally left the table name, local key name, and foreign key name parameters null).
    }
}