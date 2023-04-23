<?php

namespace CodeSleeve\Holloway\Tests\Fixtures\Mappers;

use stdClass;
use CodeSleeve\Holloway\Tests\Fixtures\Entities\{Pack, Pup, Collar};

class PackMapper extends Mapper
{
    protected string $table = 'packs';
    protected string $entityClassName = Pack::class;
    protected bool $hasTimestamps = false;

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
        }, 
        fn(stdClass $pack, stdClass $collar) => $pack->id = $collar->pack_id, 
        Collar::class);
    }

    /**
     * @return string
     */
    public function getEntityClassName() : string
    {
        return $this->entityClassName;
    }
}