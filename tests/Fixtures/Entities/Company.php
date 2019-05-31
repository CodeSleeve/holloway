<?php

namespace CodeSleeve\Tests\Holloway\Fixtures\Entities;

use Illuminate\Support\Collection;

class Company extends Entity
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $foundedAt;

    /**
     * @var Collection|null
     */
    protected $users;

    /**
     * @param int        $id
     * @param string     $foundedAt
     * @param Collection $users
     */
    public function __construct(int $id, string $foundedAt)
    {
        $this->id = $id;
        $this->foundedAt = $foundedAt;
    }

    /**
     * @return int
     */
    public function id() : int
    {
        return $this->id;
    }

    /**
     * @param int $value
     * @return void
     */
    public function setId(int $value) : void
    {
        $this->id = $value;
    }

    /**
     * @return string
     */
    public function foundedAt() : string
    {
        return $this->foundedAt;
    }

    /**
     * @return Collection|null
     */
    public function users() : ?Collection
    {
        return $this->users;
    }
}