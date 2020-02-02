<?php

namespace CodeSleeve\Holloway\Tests\Fixtures\Entities;

use Illuminate\Support\Collection;

class Company extends Entity
{
    protected string $founded_at;
    protected ?Collection $users;

    /**
     * @param string     $foundedAt
     * @param Collection $users
     */
    public function __construct(string $foundedAt)
    {
        $this->founded_at = $foundedAt;
    }
}