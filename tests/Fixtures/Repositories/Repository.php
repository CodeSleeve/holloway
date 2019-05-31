<?php

namespace CodeSleeve\Tests\Holloway\Fixtures\Repositories;

use CodeSleeve\Holloway\Holloway;
use Illuminate\Support\Collection;
use CodeSleeve\Tests\Holloway\Fixtures\Entities\Entity;
use Illuminate\Contracts\Pagination\Paginator;

abstract class Repository
{
    /**
     * @var string
     */
    protected $entityClassName = '';

    /**
     * @var Mapper
     */
    protected $mapper;

    /**
     * @return  void
     */
    public function __construct()
    {
        $this->mapper = Holloway::instance()->getMapper($this->entityClassName);
    }

    /**
     * @return Collection
     */
    public function all() : Collection
    {
        return $this->mapper->all();
    }

    /**
     * @param  int|integer $perPage
     * @return Paginator
     */
    public function paginate(int $perPage = 15) : Paginator
    {
        return $this->mapper->paginate($perPage);
    }

    /**
     * @param  string $field
     * @param  string $value
     * @return Collection
     */
    public function findBy(string $field, string $value) : Collection
    {
        return $this->mapper->where($field, $value)->get();
    }

    /**
     * @param  string $field
     * @param  string $value
     * @return Entity|null
     */
    public function findOneBy(string $field, string $value) : ?Entity
    {
        return $this->mapper->where($field, $value)->first();
    }

    /**
     * @return bool
     */
    public function remove(Entity $entity)
    {
        return $this->mapper->remove($entity);
    }
}