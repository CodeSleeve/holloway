<?php

namespace CodeSleeve\Holloway\Tests\Fixtures\Mappers;

use CodeSleeve\Holloway\Tests\Fixtures\Entities\Pack;

class PackWithoutTableMapper extends Mapper
{
    protected string $entityClassName = Pack::class;
}
