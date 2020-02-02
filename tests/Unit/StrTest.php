<?php

namespace CodeSleeve\Holloway\Tests\Unit;

use PHPUnit\Framework\TestCase;
use CodeSleeve\Holloway\Functions\Str;

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