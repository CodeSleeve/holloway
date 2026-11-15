<?php

namespace CodeSleeve\Holloway\Tests\Fixtures\Mappers;

use stdClass;
use CodeSleeve\Holloway\Tests\Fixtures\Entities\{ExtendedPack, Pup};

class ExtendedPackMapper extends Mapper
{
    protected string $table = 'packs';
    protected string $entityClassName = ExtendedPack::class;
    protected bool $hasTimestamps = false;

    /**
     * Custom relationships that are defined with callables other than closures.
     */
    public function defineRelations() : void
    {
        $this->hasMany('pups', Pup::class, 'pack_id', 'id');

        $this->customOne('latestPup', [static::class, 'loadPups'], [static::class, 'matchPup'], Pup::class);
        $this->customMany('pupNames', [static::class, 'loadPups'], [static::class, 'matchPup'], [static::class, 'nameOf']);
    }

    public static function loadPups($query, $packs)
    {
        return $query->from('pups')->whereIn('pack_id', $packs->pluck('id'))->orderByDesc('id')->get();
    }

    public static function matchPup(stdClass $pack, stdClass $pup) : bool
    {
        return $pack->id == $pup->pack_id;
    }

    public static function nameOf(stdClass $pup) : string
    {
        return strtoupper($pup->first_name);
    }
}
