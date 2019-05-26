<?php

namespace Tests\Fixtures\Entities;

class Collar extends Entity
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var int
     */
    protected $pupId;

    /**
     * @var string
     */
    protected $color;

    /**
     * @param int    $id
     * @param int    $pupId
     * @param string $color
     */
    public function __construct(int $id, int $pupId, string $color)
    {
        $this->id = $id;
        $this->pupId = $pupId;
        $this->color = $color;
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
     * @return int
     */
    public function pupId() : int
    {
        return $this->pupId;
    }

    /**
     * @return string
     */
    public function color() : string
    {
        return $this->color;
    }
}