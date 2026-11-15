<?php

namespace CodeSleeve\Holloway\Tests\Fixtures\Mappers;

class PackWithPupsCollarMapper extends PackMapper
{
    protected array $with = ['pups.collar'];
}