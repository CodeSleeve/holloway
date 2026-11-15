<?php

namespace CodeSleeve\Holloway\Tests\Fixtures\Mappers;

class PupWithCollarCompanyMapper extends PupMapper
{
    protected array $with = ['collar.company'];
}