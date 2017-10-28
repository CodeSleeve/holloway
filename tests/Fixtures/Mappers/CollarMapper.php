<?php

namespace Tests\Fixtures\Mappers;

use Holloway\Mapper;
use Tests\Fixtures\Entities\{Collar, Pup};

class CollarMapper extends Mapper
{
    /**
     * string $tableName
     */
    protected $tableName = 'collars';

    /**
     * @var string
     */
    protected $entityClassName = Collar::class;

    /**
     * Return the identifier (primary key) for a given entity.
     *
     * @param  Collar $entity
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
     * @return  void
     */
    public function setRelations()
    {
        $this->belongsTo('pup', Pup::class, 'pup_id', 'id');    // A collar belongs to a pup.
    }

    /**
     * @return string
     */
    public function getEntityClassName() : string
    {
        return $this->entityClassName;
    }
}