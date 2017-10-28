<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Illuminate\Database\Capsule\Manager as Capsule;

abstract class TestCase extends BaseTestCase
{
    /**
     * @return void
     */
    public function setUp()
    {
        Capsule::beginTransaction();
    }

    /**
     * @return void
     */
    public function tearDown()
    {
        Capsule::rollBack();
    }
}