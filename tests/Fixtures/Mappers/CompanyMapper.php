<?php

namespace Tests\Fixtures\Mappers;

use Illuminate\Support\Collection;
use Holloway\Mapper;
use Tests\Fixtures\Entities\{Company, PupFood};
use stdClass;

class CompanyMapper extends Mapper
{
    /**
     * string $table
     */
    protected $table = 'companies';

    /**
     * @var string
     */
    protected $entityClassName = Company::class;

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
     * @param  mixed $entity
     * @return array
     */
    public function dehydrate($entity) : array
    {
        return [
            'id'         => $entity->id(),
            'founded_at' => $entity->foundatedAt()
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
            $record->users = $relations['users'] ?? null;
        }

        return new $className(...array_values((array) $record));
    }

    /**
     * @return  void
     */
    public function setRelations()
    {
        $this->hasMany('pupFoods', PupFood::class);    // A company has many pup foods (NOTE: For testing purposes, we've intentionally left the table name, local key name, and foreign key name parameters null).
    }

    /**
     * @return string
     */
    public function getEntityClassName() : string
    {
        return $this->entityClassName;
    }
}