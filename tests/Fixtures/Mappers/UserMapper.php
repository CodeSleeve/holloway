<?php

namespace CodeSleeve\Holloway\Tests\Fixtures\Mappers;

use CodeSleeve\Holloway\Tests\Fixtures\Entities\{Pup, User};

class UserMapper extends Mapper
{
    protected string $table = 'users';
    protected string $entityClassName = User::class;

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
     * @return  void
     */
    public function defineRelations() : void
    {
        $this->belongsToMany('pups', Pup::class, 'pups_users', 'user_id', 'pup_id');                       // A user belongs to many pups.
        $this->belongsToMany('surrogatePups', Pup::class, 'surrogate_pups_users', 'user_id', 'pup_id');    // A user belongs to many pups that they may care for (surrogate)
    }
}