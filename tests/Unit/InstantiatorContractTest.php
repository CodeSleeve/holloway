<?php

namespace CodeSleeve\Holloway\Tests\Unit;

use Error;
use CodeSleeve\Holloway\Tests\Fixtures\Entities\Pup;
use CodeSleeve\Holloway\Tests\Fixtures\Mappers\PupMapper;
use CodeSleeve\Holloway\Tests\Fixtures\Mappers\UninitializedInstantiatorMapper;

/**
 * Documents the current contract: Holloway's base Mapper declares an $instantiator
 * but leaves it to the consuming application's mapper to assign it.
 */
class InstantiatorContractTest extends TestCase
{
    /** @test */
    public function the_base_mapper_does_not_initialize_the_instantiator_itself()
    {
        // given
        $mapper = new UninitializedInstantiatorMapper;

        // then
        $this->expectException(Error::class);
        $this->expectExceptionMessage('must not be accessed before initialization');

        // when
        $mapper->instantiateEntity([]);
    }

    /** @test */
    public function a_mapper_that_assigns_the_instantiator_can_instantiate_entities_without_calling_their_constructors()
    {
        // given
        $mapper = new PupMapper;   // The fixture base mapper assigns an Instantiator in its constructor.

        // when
        $pup = $mapper->instantiateEntity([]);   // Pup's constructor requires arguments; none are passed.

        // then
        $this->assertInstanceOf(Pup::class, $pup);
    }
}
