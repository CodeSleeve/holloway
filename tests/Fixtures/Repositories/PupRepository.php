<?php

namespace CodeSleeve\Tests\Holloway\Fixtures\Repositories;

use CodeSleeve\Tests\Holloway\Fixtures\Entities\Pup;

class PupRepository extends Repository
{
    /**
     * @var string
     */
    protected $entityClassName = Pup::class;
}