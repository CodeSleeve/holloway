<?php

namespace CodeSleeve\Holloway\Tests\Fixtures\Repositories;

use CodeSleeve\Holloway\Tests\Fixtures\Entities\Pup;

class PupRepository extends Repository
{
    /**
     * @var string
     */
    protected $entityClassName = Pup::class;
}