<?php

namespace CodeSleeve\Holloway\Tests\Fixtures\Mappers;

use stdClass;
use Illuminate\Support\Collection;
use CodeSleeve\Holloway\Mapper as BaseMapper;
use CodeSleeve\Holloway\Tests\Fixtures\Entities\Pup;

/**
 * Extends Holloway's base mapper directly, and so never assigns $instantiator.
 */
class UninitializedInstantiatorMapper extends BaseMapper
{
    protected string $entityClassName = Pup::class;

    public function getEntityClassName() : string
    {
        return $this->entityClassName;
    }

    public function defineRelations() : void
    {
    }

    public function getIdentifier($entity)
    {
        return $entity->getId();
    }

    public function setIdentifier($entity, $value) : void
    {
        $entity->setId($value);
    }

    public function hydrate(stdClass $record, Collection $relations)
    {
        return $this->instantiateEntity((array) $record);
    }

    public function dehydrate($entity) : array
    {
        return [];
    }
}
