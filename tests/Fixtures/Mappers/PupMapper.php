<?php

namespace Tests\Fixtures\Mappers;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\Fixtures\Entities\{Pup, Collar, Pack, PupFood};
use stdClass;

class PupMapper extends Mapper
{
    /**
     * string $table
     */
    protected $table = 'pups';

    /**
     * @var string
     */
    protected $entityClassName = Pup::class;

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
        $entity->id($value);
    }

    /**
     * @param  mixed $entity
     * @return array
     */
    public function dehydrate($entity) : array
    {
        return [
            'id'         => $entity->id(),
            'pack_id'    => $entity->packId(),
            'first_name' => $entity->firstName(),
            'last_name'  => $entity->lastName(),
            'coat'       => $entity->coat(),
            'created_at' => $entity->createdAt(),
            'updated_at' => $entity->updatedAt()
        ];
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
            $record->collar = $relations['collar'] ?? null;
            $record->pack = $relations['pack'] ?? null;
        }

        $entity = new $className(...array_values(array_except((array) $record, ['created_at', 'updated_at'])));
        $entity->setTimestamps(Carbon::createFromFormat('Y-m-d H:i:s', $record->created_at), Carbon::createFromFormat('Y-m-d H:i:s', $record->updated_at));

        return $entity;
    }

    /**
     * @return  void
     */
    public function defineRelations()
    {
        $this->hasOne('collar', Collar::class, 'pup_id', 'id');    // A pup has one collar.
        $this->belongsTo('pack', Pack::class, 'pack_id', 'id');    // A pup belongs to a pack.
        $this->belongsToMany('pupFoods', PupFood::class);          // A pup belongs to many foods (NOTE: For testing purposes, we've intentionally left the table name, local key name, and foreign key name parameters null).
    }

    /**
     * @return string
     */
    public function getEntityClassName() : string
    {
        return $this->entityClassName;
    }

    /**
     * Scope a query to only include pups of a given coat color.
     *
     * @param Builder $query
     * @param string  $coat
     * @return Builder
     */
    public function scopeOfCoat($query, string $coat)
    {
        return $query->where('coat', $coat);
    }
}