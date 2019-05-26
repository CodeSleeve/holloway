<?php

namespace Tests\Fixtures\Repositories;

use Tests\Fixtures\Entities\Pup;

class PupRepository extends Repository
{
    /**
     * @var string
     */
    protected $entityClassName = Pup::class;
}