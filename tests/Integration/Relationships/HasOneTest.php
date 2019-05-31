<?php

namespace CodeSleeve\Tests\Holloway\Integration\HasOne;

use CodeSleeve\Holloway\Holloway;
use CodeSleeve\Holloway\Relationships\HasOne;
use CodeSleeve\Tests\Holloway\Fixtures\Entities\Collar;
use CodeSleeve\Tests\Holloway\Fixtures\Mappers\CollarMapper;
use CodeSleeve\Tests\Holloway\Helpers\CanBuildTestFixtures;
use CodeSleeve\Tests\Holloway\Integration\TestCase;

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
        $relationship = new HasOne('collar', 'collars', 'pup_id', 'id', Collar::class, $collarMapper->toBase());

        // when
        $relationship->load($records, function() {});
        $collar = $relationship->getData()->first();

        // then
        $this->assertEquals(1, $collar->id);
        $this->assertEquals('black', $collar->color);
    }
}