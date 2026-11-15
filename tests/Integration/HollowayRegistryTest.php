<?php

namespace CodeSleeve\Holloway\Tests\Integration;

use ReflectionClass;
use UnexpectedValueException;
use CodeSleeve\Holloway\Holloway;
use CodeSleeve\Holloway\Tests\Fixtures\Entities\{Collar, Pack, Pup};
use CodeSleeve\Holloway\Tests\Fixtures\Mappers\{CollarMapper, CompanyMapper, PackMapper, PupFoodMapper, PupMapper, UserMapper};

class HollowayRegistryTest extends TestCase
{
    protected array $originalMappers;

    public function setUp() : void
    {
        parent::setUp();

        // The registry is shared by every test, so put back whatever was registered afterwards.
        $this->originalMappers = Holloway::instance()->getMappers();
    }

    public function tearDown() : void
    {
        Holloway::instance()->setMappers($this->originalMappers);

        parent::tearDown();
    }

    /** @test */
    public function it_registers_mappers_given_as_an_array_and_defines_their_relations()
    {
        // when
        Holloway::instance()->register([CollarMapper::class, CompanyMapper::class, PackMapper::class, PupFoodMapper::class, PupMapper::class, UserMapper::class]);

        // then
        $this->assertCount(6, Holloway::instance()->getMappers());
        $this->assertInstanceOf(PupMapper::class, Holloway::instance()->getMapper(Pup::class));
        $this->assertTrue(Holloway::instance()->getMapper(Pup::class)->hasRelationship('collar'));
        $this->assertTrue(Holloway::instance()->getMapper(Pack::class)->hasRelationship('pups'));
    }

    /** @test */
    public function it_registers_mappers_given_as_separate_arguments()
    {
        // when
        Holloway::instance()->register(CollarMapper::class, CompanyMapper::class, PackMapper::class, PupFoodMapper::class, PupMapper::class, UserMapper::class);

        // then
        $this->assertCount(6, Holloway::instance()->getMappers());
        $this->assertSame(Collar::class, Holloway::instance()->getMapper(Collar::class)->getEntityClassName());
    }

    /** @test */
    public function it_replaces_the_mappers_that_were_registered_before()
    {
        // given
        Holloway::instance()->register([CollarMapper::class, CompanyMapper::class, PackMapper::class, PupFoodMapper::class, PupMapper::class, UserMapper::class]);

        // when
        Holloway::instance()->register([CollarMapper::class, CompanyMapper::class, PackMapper::class, PupFoodMapper::class, PupMapper::class]);

        // then
        $this->assertCount(5, Holloway::instance()->getMappers());
    }

    /** @test */
    public function it_returns_the_mapper_for_an_entity_instance_as_well_as_for_its_class_name()
    {
        // given
        Holloway::instance()->register([CollarMapper::class, CompanyMapper::class, PackMapper::class, PupFoodMapper::class, PupMapper::class, UserMapper::class]);
        $pup = (new ReflectionClass(Pup::class))->newInstanceWithoutConstructor();

        // then
        $this->assertSame(Holloway::instance()->getMapper(Pup::class), Holloway::instance()->getMapper($pup));
    }

    /** @test */
    public function it_throws_when_there_is_no_mapper_for_an_entity()
    {
        // given
        Holloway::instance()->setMappers([]);

        // then
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage("Unknown entity " . Pup::class);

        // when
        Holloway::instance()->getMapper(Pup::class);
    }

    /** @test */
    public function it_knows_whether_a_mapper_is_registered_for_an_entity()
    {
        // given
        $pup = (new ReflectionClass(Pup::class))->newInstanceWithoutConstructor();
        Holloway::instance()->register([CollarMapper::class, CompanyMapper::class, PackMapper::class, PupFoodMapper::class, PupMapper::class, UserMapper::class]);
        $unregistered = new class {};

        // then
        $this->assertTrue(Holloway::instance()->hasMapperFor(Pup::class));
        $this->assertTrue(Holloway::instance()->hasMapperFor($pup));
        $this->assertFalse(Holloway::instance()->hasMapperFor('Not\An\Entity'));
        $this->assertFalse(Holloway::instance()->hasMapperFor($unregistered));
    }

    /** @test */
    public function it_can_have_its_mappers_set_and_flushed()
    {
        // given
        $mapper = new PupMapper;

        // when
        Holloway::instance()->setMappers([Pup::class => $mapper]);

        // then
        $this->assertSame([Pup::class => $mapper], Holloway::instance()->getMappers());

        // when
        Holloway::instance()->flush();

        // then
        $this->assertSame([], Holloway::instance()->getMappers());
    }

    /** @test */
    public function it_always_returns_the_same_instance()
    {
        $this->assertSame(Holloway::instance(), Holloway::instance());
    }
}
