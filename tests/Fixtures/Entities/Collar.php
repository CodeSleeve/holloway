<?php

namespace CodeSleeve\Holloway\Tests\Fixtures\Entities;

class Collar extends Entity
{
    protected ?Pup $pup;
    protected int $pup_id;
    protected string $color;

    /**
     * @param Pup $pup
     * @param string $color
     */
    public function __construct(Pup $pup, string $color)
    {
        $this->setPup($pup);
        $this->color = $color;
    }

    /**
     * @param Pup $pup
     * @return void
     */
    public function setPup(Pup $pup)
    {
        $this->pup_id = $pup->id;
        $this->pup = $pup;
    }

    /**
     * @param string $color
     * @return void
     */
    public function setColor(string $color)
    {
        $this->color = $color;
    }
}