<?php

namespace CodeSleeve\Tests\Holloway\Fixtures\Mappers;

use Carbon\Carbon;
use CodeSleeve\Holloway\SoftDeletes;
use Illuminate\Support\Collection;
use CodeSleeve\Tests\Holloway\Fixtures\Entities\{Pack, Pup, Collar};
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
            $record->collars = $relations['collars'] ?? null;
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

        $this->customMany('collars', function($query, $packs) {
            return $query->from('collars')
                ->select('collars.*', 'pups.pack_id')
                ->join('pups', 'collars.pup_id', '=', 'pups.id')
                ->join('packs', 'pups.pack_id', '=', 'packs.id')
                ->whereIn('packs.id', $packs->pluck('id'))
                ->get();
        }, function(stdClass $pack, stdClass $collar) {
            return $pack->id = $collar->pack_id;
        }, function(stdClass $collar){
            return new Collar($collar->id, $collar->pup_id, $collar->color);
        });
    }

    /**
     * @return string
     */
    public function getEntityClassName() : string
    {
        return $this->entityClassName;
    }
}