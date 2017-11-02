<?php

namespace Tests\Fixtures\Mappers;

use Carbon\Carbon;
use Holloway\SoftDeletes;
use Illuminate\Support\Collection;
use Tests\Fixtures\Entities\{Pack, Pup};
use stdClass;

class PackMapper extends Mapper
{
    use SoftDeletes;

    /**
     * string $table
     */
    protected $table = 'packs';

    /**
     * @var string
     */
    protected $entityClassName = Pack::class;

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
        $this->id = $value;
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
     * @param  stdClass   $record
     * @param  Collection $relations
     * @return mixed
     */
    public function hydrate(stdClass $record, Collection $relations)
    {
        $className = $this->entityClassName;

        if ($relations->count()) {
            $record->pups = $relations['pups'] ?? null;
        }

        $entity = new $className(...array_values(array_except((array) $record, ['created_at', 'updated_at', 'deleted_at'])));
        $entity->setTimestamps(Carbon::createFromFormat('Y-m-d H:i:s', $record->created_at), Carbon::createFromFormat('Y-m-d H:i:s', $record->updated_at));

        return $entity;
    }

    /**
     * @return  void
     */
    public function defineRelations()
    {
        $this->hasMany('pups', Pup::class, 'pack_id', 'id');    // A pack has many pups.
    }

    /**
     * @return string
     */
    public function getEntityClassName() : string
    {
        return $this->entityClassName;
    }
}