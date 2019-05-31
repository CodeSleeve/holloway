<?php

namespace CodeSleeve\Tests\Holloway\Fixtures\Entities;

use Carbon\Carbon;

class Pup extends Entity
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var int
     */
    protected $packId;

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
    protected $coat;

    /**
     * @var Collar
     */
    protected $collar;

    /**
     * @var Pack
     */
    protected $pack;

    /**
     * @param int         $id
     * @param int         $packId
     * @param string      $firstName
     * @param string      $lastName
     * @param string      $coat
     * @param Collar|null $collar
     * @param Pack|null   $pack
     */
    public function __construct(int $id, int $packId, string $firstName, string $lastName, string $coat, ?Collar $collar = null, ?Pack $pack = null)
    {
        $this->id = $id;
        $this->packId = $packId;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->coat = $coat;
        $this->createdAt = Carbon::now();
        $this->updatedAt = Carbon::now();

        $this->collar = $collar;
        $this->pack = $pack;
    }

    /**
     * @param  int|null $id
     * @return int
     */
    public function id(?int $id = null) : int
    {
        if ($id) {
            $this->id = $id;
        }

        return $this->id;
    }

    /**
     * @param  int|null $packId
     * @return int
     */
    public function packId(?int $packId = null) : int
    {
        if ($packId) {
            $this->packId = $packId;
        }

        return $this->packId;
    }

    /**
     * @param  string|null $firstName
     * @return string
     */
    public function firstName(?string $firstName = null) : string
    {
        if ($firstName) {
            $this->firstName = $firstName;
        }

        return $this->firstName;
    }

    /**
     * @param  string|null $lastName
     * @return string
     */
    public function lastName(?string $lastName = null) : string
    {
        if ($lastName) {
            $this->lastName = $lastName;
        }

        return $this->lastName;
    }

    /**
     * @param  string|null $lastName
     * @return string
     */
    public function coat(?string $coat = null) : string
    {
        if ($coat) {
            $this->coat = $coat;
        }

        return $this->coat;
    }

    /**
     * @param  Collar|null $collar
     * @return Collar|null
     */
    public function collar(?Collar $collar = null) : ?Collar
    {
        if ($collar) {
            $this->collar = $collar;
        }

        return $this->collar;
    }

    /**
     * @param  Pack|null $pack
     * @return Pack
     */
    public function pack(?Pack $pack = null) : ?Pack
    {
        if ($pack) {
            $this->pack = $pack;
        }

        return $this->pack;
    }
}
