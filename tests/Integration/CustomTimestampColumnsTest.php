<?php

namespace CodeSleeve\Holloway\Tests\Integration;

use CodeSleeve\Holloway\Tests\Fixtures\Entities\StampedNote;
use CodeSleeve\Holloway\Tests\Fixtures\Mappers\StampedNoteMapper;
use CodeSleeve\Holloway\Tests\Helpers\CanBuildTestFixtures;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * A mapper can override CREATED_AT and UPDATED_AT. The created column is set when an entity is
 * inserted and must never be rewritten by an update; the updated column is refreshed by both.
 */
class CustomTimestampColumnsTest extends TestCase
{
    use CanBuildTestFixtures;   // sets up the event manager and the transaction that these tests need

    /** @test */
    public function it_sets_both_custom_timestamp_columns_when_inserting_an_entity()
    {
        // given
        $mapper = new StampedNoteMapper;

        // when
        $mapper->store($note = new StampedNote('first'));

        // then
        $row = Capsule::table('stamped_notes')->find($note->id);
        $this->assertNotNull($row->date_created);
        $this->assertNotNull($row->date_modified);
    }

    /** @test */
    public function it_does_not_rewrite_the_custom_created_column_when_updating_an_entity()
    {
        // given: a stored note that is loaded again by an entity that doesn't carry the created timestamp
        $mapper = new StampedNoteMapper;
        $mapper->store($note = new StampedNote('first'));
        $created = Capsule::table('stamped_notes')->find($note->id)->date_created;

        $loaded = $mapper->find($note->id);
        $loaded->name = 'renamed';
        $loaded->date_created = null;

        // when
        $mapper->store($loaded);

        // then: the stored creation time is kept
        $row = Capsule::table('stamped_notes')->find($note->id);
        $this->assertSame('renamed', $row->name);
        $this->assertSame($created, $row->date_created);
    }

    /** @test */
    public function it_refreshes_the_custom_updated_column_when_updating_an_entity()
    {
        // given
        $mapper = new StampedNoteMapper;
        $mapper->store($note = new StampedNote('first'));
        Capsule::table('stamped_notes')->where('id', $note->id)->update(['date_modified' => '2000-01-01 00:00:00']);

        $loaded = $mapper->find($note->id);
        $loaded->name = 'renamed';

        // when
        $mapper->store($loaded);

        // then
        $this->assertNotSame('2000-01-01 00:00:00', Capsule::table('stamped_notes')->find($note->id)->date_modified);
    }

    /** @test */
    public function it_leaves_the_custom_created_column_out_of_the_update_statement()
    {
        // given
        $mapper = new StampedNoteMapper;
        $mapper->store($note = new StampedNote('first'));
        $loaded = $mapper->find($note->id);
        $loaded->name = 'renamed';

        $connection = Capsule::connection();
        $connection->enableQueryLog();
        $connection->flushQueryLog();

        // when
        $mapper->store($loaded);

        // then
        $updates = array_values(array_filter($connection->getQueryLog(), fn($query) => stripos($query['query'], 'update') === 0));
        $this->assertCount(1, $updates);
        $this->assertStringNotContainsString('date_created', $updates[0]['query']);
        $this->assertStringContainsString('date_modified', $updates[0]['query']);
    }
}
