<?php

namespace Tests\Fixtures\Entities;

use Carbon\Carbon;

abstract class Entity
{
    /**
     * @var Carbon
     */
    protected $createdAt;

    /**
     * @var Carbon
     */
    protected $updatedAt;

    /**
     * @param iterable $relationships
     */
    public function setRelationships(iterable $relationships)
    {
        // NOP
    }

    /**
     * @param Carbon $createdAt
     * @param Carbon $updatedAt
     */
    public function setTimestamps(Carbon $createdAt, Carbon $updatedAt)
    {
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    /**
     * @return Carbon
     */
    public function createdAt() : Carbon
    {
        return $this->createdAt;
    }

    /**
     * @return Carbon
     */
    public function updatedAt() : ?Carbon
    {
        return $this->updatedAt;
    }
}