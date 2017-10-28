<?php

namespace Tests\Fixtures\Repositories;

use Holloway\Repository;
use Tests\Fixtures\Entities\Pup;

class PupRepository extends Repository
{
    /**
     * @var string
     */
    protected $entityClassName = Pup::class;
}