<?php

namespace Tests\Fixtures\Entities;

use Illuminate\Support\Collection;
use Holloway\Entities\Entity;

class Pack extends Entity
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var Collection|null
     */
    protected $pups;

    /**
     * @var Collection|null
     */
    protected $collars;

    /**
     * @param int    $id
     * @param string $name
     * @param Collection|null
     */
    public function __construct(int $id, string $name, ?Collection $pups = null, ?Collection $collars = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->pups = $pups;
        $this->collars = $collars;
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
     * @param  string|null $name
     * @return string
     */
    public function name(?string $name = null) : string
    {
        if ($name) {
            $this->name = $name;
        }

        return $this->name;
    }

    /**
     * @param  Collection|null $pups
     * @return Collection
     */
    public function pups(?Collection $pups = null) : ?Collection
    {
        if ($pups) {
            $this->pups = $pups;
        }

        return $this->pups;
    }

    /**
     * @param  Collection|null $collars
     * @return Collection
     */
    public function collars(?Collection $collars = null) : ?Collection
    {
        if ($collars) {
            $this->collars = $collars;
        }

        return $this->collars;
    }
}