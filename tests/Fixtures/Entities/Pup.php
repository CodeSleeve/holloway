<?php

namespace CodeSleeve\Holloway\Tests\Fixtures\Entities;

class Pup extends Entity
{
    use HasTimestamps;
    
    protected string $firstName;
    protected string $lastName;
    protected string $coat;
    protected ?Collar $collar;
    protected ?Pack $pack;
    protected int $pack_id;

    /**
     * @param Pack $pack
     * @param string $firstName
     * @param string $lastName
     * @param string $coat
     * @param Collar|null $collar
     */
    public function __construct(Pack $pack,  string $firstName, string $lastName, string $coat, ?Collar $collar = null)
    {
        $this->setPack($pack);
        $this->first_name = $firstName;
        $this->last_name = $lastName;
        $this->coat = $coat;
        $this->collar = $collar;
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
     * @param string $coat
     * @return void
     */
    public function setCoat(string $coat)
    {
        $this->coat = $coat;
    }
    
    /**
     * @param Collar $collar
     * @return void
     */
    public function setCollar(Collar $collar)
    {
        $this->collar = $collar;
    }

    /**
     * @param Pack $pack
     * @return void
     */
    public function setPack(Pack $pack)
    {
        $this->pack = $pack;
        $this->pack_id = $pack->id;
    }
}
