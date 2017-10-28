<?php

namespace Tests\Unit;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Holloway\Functions\Str;
use Holloway\Builder;

class StrTest extends TestCase
{
    /** @test */
    public function it_can_chomp_a_string()
    {
        // given
        $expected = [
            'foo.bar.baz',
            'foo.bar',
            'foo'
        ];

        // when
        $actual = Str::chomp('.', 'foo.bar.baz.qux');

        // then
        $this->assertEquals($expected, $actual);
    }
}