<?php

namespace Tests\Fixtures\Mappers;

use Holloway\Mapper as BaseMapper;
use Illuminate\Support\Collection;
use stdClass;

abstract class Mapper extends BaseMapper
{
    /**
     * Return the identifier (primary key) for a given entity.
     *
     * @param  mixed $entity
     * @return mixed
     */
    public function getIdentifier($entity)
    {
        return $entity->getId();
    }

    /**
     * Set the identifier (primary key) for a given entity.
     *
     * @param mixed $value
     * @param mixed $entity
     * @return void
     */
    public function setIdentifier($entity, $value) : void
    {
        $entity->setId($value);
    }

    /**
     * @param  stdClass   $record
     * @param  Collection $relationships
     * @return mixed
     */
    public function hydrate(stdClass $record, Collection $relationships)
    {
        // By default, this method will simply new up an instance of the entity class,
        // passing each value stdClass objct as a constructor property. If you wish to
        // do anything more complex than this (hydrating value objects, etc) you should
        // override the make method on the child entity map class.
        $className = $this->getEntityClassName();

        return new $className(...array_values((array) $record));
    }

    /**
     * @param  mixed $entity
     * @return array
     */
    public function dehydrate($entity) : array
    {
        return $entity->toArray();
    }
}