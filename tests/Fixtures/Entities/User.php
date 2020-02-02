<?php

namespace CodeSleeve\Holloway\Tests\Fixtures\Entities;

use Illuminate\Support\Collection;

class User extends Entity
{
    protected string $first_name;
    protected $last_name;
    protected $email;
    protected ?Collection $pups;
    protected ?Collection $surrogatePups;

    /**
     * @param int    $id
     * @param string $firstName
     * @param string $lastName
     * @param string $email
     */
    public function __construct(int $id, string $firstName, string $lastName, string $email)
    {
        $this->id = $id;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
    }

    /**
     * @param string $firstName
     * @return void
     */
    public function setFirstName(string $firstName)
    {
        $this->first_name = $firstName;
    }

    /**
     * @param string $lastName
     * @return void
     */
    public function setLastName(string $lastName)
    {
        $this->last_name = $lastName;
    }

    /**
     * @param string $email
     * @return void
     */
    public function setEmail(string $email)
    {
        $this->email = $email;
    }

    /**
     * @param Collection|null $pups
     * @return void
     */
    public function setPups(?Collection $pups)
    {
        $this->pups = $pups;
    }

    /**
     * @param Collection|null $surrogatePups
     * @return void
     */
    public function setSurrogatePups(?Collection $surrogatePups)
    {
        $this->surrogatePups = $surrogatePups;
    }
}