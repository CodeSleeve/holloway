<?php

namespace CodeSleeve\Holloway\Tests\Fixtures\Mappers;

use Carbon\CarbonImmutable;
use stdClass;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use CodeSleeve\Holloway\Mapper as BaseMapper;
use Doctrine\Instantiator\Instantiator;

abstract class Mapper extends BaseMapper
{
    /** @var Instantiator */
    protected $instantiator;
    
    /**
     * @param Instantiator|null $instantiator
     */
    public function __construct(?Instantiator $instantiator = null)
    {
        parent::__construct();

        $this->instantiator = $instantiator ?: new Instantiator();
    }

    /**
     * @return string
     */
    public function getEntityClassName() : string
    {
        return $this->entityClassName;
    }

    /**
     * Return the identifier (primary key) for a given entity.
     *
     * @param  mixed $entity
     * @return mixed
     */
    public function getIdentifier($entity)
    {
        return $entity->id;
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
        $attributes = array_merge((array) $record, $relationships->all());

        if ($this->hasTimestamps) {
            $attributes['created_at'] = new CarbonImmutable($record->created_at);
            $attributes['updated_at'] = new CarbonImmutable($record->updated_at);
        }

        $object = $this->instantiateEntity($attributes);

        return $object->mapperFill($attributes);
    }

    /**
     * @param  mixed $entity
     * @return array
     */
    public function dehydrate($entity) : array
    {
        $attributes = Arr::except($entity->toArray(), array_map(fn($relationship) => $relationship->getName(), $this->relationships));

        return $attributes;
    }
}