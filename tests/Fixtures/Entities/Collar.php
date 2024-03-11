<?php

namespace CodeSleeve\Holloway\Tests\Fixtures\Entities;

class Collar extends Entity
{
    use HasTimestamps;
    
    protected ?Pup $pup;
    protected int $pup_id;
    protected string $color;

    public function __construct(Pup $pup, string $color)
    {
        $this->setPup($pup);
        $this->color = $color;
    }

    public function setPup(Pup $pup) : void
    {
        $this->pup_id = $pup->id;
        $this->pup = $pup;
    }

    public function setColor(string $color) : void
    {
        $this->color = $color;
    }
}