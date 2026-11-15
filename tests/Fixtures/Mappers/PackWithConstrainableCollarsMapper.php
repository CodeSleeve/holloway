<?php

namespace CodeSleeve\Holloway\Tests\Fixtures\Mappers;

use stdClass;
use CodeSleeve\Holloway\Tests\Fixtures\Entities\Collar;

class PackWithConstrainableCollarsMapper extends PackMapper
{
    /**
     * A custom relationship whose load closure takes the constraints as a third argument and applies them itself.
     */
    public function defineRelations() : void
    {
        parent::defineRelations();

        $this->customMany('collars', function($query, $packs, $constraints) {
            $query->from('collars')
                ->select('collars.*', 'pups.pack_id')
                ->join('pups', 'collars.pup_id', '=', 'pups.id')
                ->whereIn('pups.pack_id', $packs->pluck('id'));

            $constraints($query);

            return $query->get();
        },
        fn(stdClass $pack, stdClass $collar) => $pack->id == $collar->pack_id,
        Collar::class);
    }
}