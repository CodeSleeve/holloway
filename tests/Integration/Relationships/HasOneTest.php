<?php

namespace CodeSleeve\Holloway\Tests\Integration\HasOne;

use CodeSleeve\Holloway\Relationships\HasOne;
use CodeSleeve\Holloway\Tests\Fixtures\Entities\Collar;
use CodeSleeve\Holloway\Tests\Fixtures\Mappers\CollarMapper;
use CodeSleeve\Holloway\Tests\Helpers\CanBuildTestFixtures;
use CodeSleeve\Holloway\Tests\Integration\TestCase;

class HasOneTest extends TestCase
{
    use CanBuildTestFixtures;

    /** @test */
    public function the_load_method_should_cause_the_relationship_to_load_the_relation_data_from_persistance_and_store_it_on_the_relationship()
    {
        // given
        $this->buildFixtures();

        $collarMapper = new CollarMapper;
        $records = collect([(object) ['id' => 1, 'pack_id' => 1, 'first_name' => 'Tobias', 'last_name' => 'Bennett']]);
        $relationship = new HasOne('collar', 'collars', 'pup_id', 'id', Collar::class, fn() => $collarMapper->newQuery());

        // when
        $relationship->load($records, function() {});
        $collar = $relationship->getData()->first();

        // then
        $this->assertEquals(1, $collar->id);
        $this->assertEquals('black', $collar->color);
    }
}