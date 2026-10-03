<?php

namespace CodeSleeve\Holloway\Tests\PhpStan;

use PHPStan\Testing\TypeInferenceTestCase;

/**
 * Runs the assertType() calls in data/*.inc through PHPStan with the
 * Holloway extension loaded.
 */
class TypeInferenceTest extends TypeInferenceTestCase
{
    /**
     * @return iterable<mixed>
     */
    public static function dataFileAsserts() : iterable
    {
        yield from self::gatherAssertTypes(__DIR__ . '/data/mapper.inc');
        yield from self::gatherAssertTypes(__DIR__ . '/data/get-mapper.inc');
    }

    /**
     * @dataProvider dataFileAsserts
     * @param mixed ...$args
     */
    public function testFileAsserts(string $assertType, string $file, ...$args) : void
    {
        $this->assertFileAsserts($assertType, $file, ...$args);
    }

    public static function getAdditionalConfigFiles() : array
    {
        return [__DIR__ . '/../../phpstan/extension.neon'];
    }
}
