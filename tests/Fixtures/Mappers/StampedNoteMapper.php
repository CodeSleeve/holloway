<?php

namespace CodeSleeve\Holloway\Tests\Fixtures\Mappers;

use stdClass;
use Illuminate\Support\Collection;
use CodeSleeve\Holloway\Mapper;
use CodeSleeve\Holloway\Tests\Fixtures\Entities\StampedNote;

/**
 * A mapper that overrides the timestamp column names (see "Custom Timestamp Columns" in the docs).
 */
class StampedNoteMapper extends Mapper
{
    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';

    protected string $table = 'stamped_notes';
    protected string $entityClassName = StampedNote::class;

    public function getEntityClassName() : string
    {
        return $this->entityClassName;
    }

    public function defineRelations() : void
    {
        //
    }

    public function getIdentifier($entity)
    {
        return $entity->id;
    }

    public function setIdentifier($entity, $value) : void
    {
        $entity->id = $value;
    }

    public function hydrate(stdClass $record, Collection $relations)
    {
        $note = new StampedNote($record->name);
        $note->id = $record->id;
        $note->date_created = $record->date_created;
        $note->date_modified = $record->date_modified;

        return $note;
    }

    public function dehydrate($entity) : array
    {
        return [
            'name' => $entity->name,
            'date_created' => $entity->date_created,
            'date_modified' => $entity->date_modified,
        ];
    }
}
