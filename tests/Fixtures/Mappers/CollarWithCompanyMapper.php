<?php

namespace CodeSleeve\Holloway\Tests\Fixtures\Mappers;

class CollarWithCompanyMapper extends CollarMapper
{
    protected array $with = ['company'];
}