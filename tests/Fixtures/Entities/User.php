<?php

namespace Tests\Fixtures\Entities;

use Illuminate\Support\Collection;

class User extends Entity
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $firstName;

    /**
     * @var string
     */
    protected $lastName;

    /**
     * @var string
     */
    protected $email;

    /**
     * @var Collection|null
     */
    protected $pups;

    /**
     * @param int    $id
     * @param string $firstName
     * @param string $lastName
     * @param string $email
     */
    public function __construct(int $id, string $firstName, string $lastName, string $email, ?Collection $pups = null)
    {
        $this->id = $id;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->pups = $pups;
    }

    /**
     * @return int
     */
    public function id() : int
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function companyId() : int
    {
        return $this->companyId;
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
    public function firstName() : string
    {
        return $this->firstName;
    }

    /**
     * @return string
     */
    public function lastName() : string
    {
        return $this->lastName;
    }

    /**
     * @return string
     */
    public function email() : string
    {
        return $this->email;
    }

    /**
     * @return Collection
     */
    public function pups() : ?Collection
    {
        return $this->pups;
    }
}