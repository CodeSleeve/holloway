<?php

namespace CodeSleeve\Holloway\Tests\Fixtures\Entities;

use Illuminate\Support\Collection;

class Pack extends Entity
{
    protected string $name;
    protected ?Collection $pups;
    protected ?Collection $collars;

    /**
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @param string $name
     * @return void
     */
    public function setName(string $name)
    {
        $this->name = $name;
    }
}