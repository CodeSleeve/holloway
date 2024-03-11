<?php

namespace CodeSleeve\Holloway\Tests\Fixtures\Entities;

class Pup extends Entity
{
    use HasTimestamps;
    
    protected string $first_name;
    protected string $last_name;
    protected string $coat;
    protected ?Collar $collar;
    protected ?Pack $pack;
    protected int $pack_id;

    public function __construct(
        Pack $pack,  
        string $first_name, 
        string $last_name, 
        string $coat, 
        ?Collar $collar = null
    ) {
        $this->setPack($pack);
        $this->first_name = $first_name;
        $this->last_name = $last_name;
        $this->coat = $coat;
        $this->collar = $collar;
    }

    public function setFirstName(string $firstName) : void
    {
        $this->first_name = $firstName;
    }

    public function setLastName(string $lastName) : void
    {
        $this->last_name = $lastName;
    }

    public function setCoat(string $coat) : void
    {
        $this->coat = $coat;
    }
    
    public function setCollar(Collar $collar) : void
    {
        $this->collar = $collar;
    }

    public function setPack(Pack $pack) : void
    {
        $this->pack = $pack;
        $this->pack_id = $pack->id;
    }
}
