<?php

namespace CodeSleeve\Holloway\Tests\Fixtures\Entities;

use Carbon\CarbonImmutable;

trait HasTimestamps
{
    protected ?CarbonImmutable $created_at;
    protected ?CarbonImmutable $updated_at;

    /**
     * @param CarbonImmutable $createdAt
     */
    public function setCreatedAt(CarbonImmutable $createdAt)
    {
        $this->created_at = $createdAt;
    }

    /**
     * @param CarbonImmutable $updatedAt
     */
    public function setUpdatedAt(CarbonImmutable $updatedAt)
    {
        $this->updated_at = $updatedAt;
    }
}