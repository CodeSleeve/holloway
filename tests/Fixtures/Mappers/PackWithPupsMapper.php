<?php

namespace CodeSleeve\Holloway\Tests\Fixtures\Mappers;

class PackWithPupsMapper extends PackMapper
{
    protected array $with = ['pups'];
}