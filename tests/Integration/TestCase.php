<?php

namespace CodeSleeve\Holloway\Tests\Integration;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Illuminate\Database\Capsule\Manager as Capsule;

abstract class TestCase extends BaseTestCase
{
    /**
     * @return void
     */
    public function setUp() : void
    {
        Capsule::beginTransaction();
    }

    /**
     * @return void
     */
    public function tearDown() : void
    {
        Capsule::rollBack();
    }
}