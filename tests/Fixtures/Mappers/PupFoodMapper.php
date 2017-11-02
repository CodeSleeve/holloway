<?php

namespace Tests\Fixtures\Mappers;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\Fixtures\Entities\{Company, Pup, PupFood};
use stdClass;

class PupFoodMapper extends Mapper
{
    /**
     * string $table
     */
    protected $table = 'pup_foods';

    /**
     * @var string
     */
    protected $entityClassName = PupFood::class;

    /**
     * Return the identifier (primary key) for a given entity.
     *
     * @param  mixed $entity
     * @return int
     */
    public function getIdentifier($entity) : int
    {
        return $entity->id();
    }

    /**
     * Set the identifier (primary key) for a given entity.
     *
     * @param mixed $entity
     * @param mixed $value
     * @return void
     */
    public function setIdentifier($entity, $value) : void
    {
        $entity->setId($value);
    }

    /**
     * @param  stdClass   $record
     * @param  Collection $relations
     * @return mixed
     */
    public function hydrate(stdClass $record, Collection $relations)
    {
        $className = $this->entityClassName;

        $entity = new $className(...array_values(array_except((array) $record, ['created_at', 'updated_at'])));
        $entity->setRelationships($relations);
        $entity->setTimestamps(Carbon::createFromFormat('Y-m-d H:i:s', $record->created_at), Carbon::createFromFormat('Y-m-d H:i:s', $record->updated_at));

        return $entity;
    }

    /**
     * @param  mixed $entity
     * @return array
     */
    public function dehydrate($entity) : array
    {
        return [];
    }

    /**
     * @return  void
     */
    public function defineRelations()
    {
        $this->belongsTo('company', Company::class);    // A pup food belongs to a company (NOTE: For testing purposes, we've intentionally left the table name, local key name, and foreign key name parameters null).
        $this->belongsToMany('pups', Pup::class);       // A pup food belongs to many pups (NOTE: For testing purposes, we've intentionally left the table name, local key name, and foreign key name parameters null).
    }

    /**
     * @return string
     */
    public function getEntityClassName() : string
    {
        return PupFood::class;
    }
}