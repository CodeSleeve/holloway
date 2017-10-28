<?php

namespace Tests\Integration;

use Holloway\Mapper;
use Holloway\Holloway;
use Tests\Fixtures\Entities\Pup;
use Tests\Fixtures\Mappers\{PupMapper, CollarMapper, PackMapper, PupFoodMapper, CompanyMapper};

class HollowayTest extends TestCase
{
    /**
     * @return  void
     */
    public static function setUpBeforeClass()
    {
        // Set up our Holloway instance and register our fixture mappers.
        Holloway::instance()->register([
            CollarMapper::class,
            CompanyMapper::class,
            PackMapper::class,
            PupFoodMapper::class,
            PupMapper::class
        ]);
    }

    /** @test */
    public function the_mapper_method_should_return_a_new_mapper_instance_when_provided_an_entity_instance()
    {
        // given
        $entity = new Pup(1, 1, 'Tobias', 'Bennett');
        $holloway = Holloway::instance();

        // when
        $mapper = $holloway->getMapper($entity);

        // then
        $this->assertInstanceOf(Mapper::class, $mapper);
    }

    /** @test */
    public function the_mapper_method_should_return_a_new_mapper_instance_when_provided_an_entity_class_name()
    {
        // given
        $holloway = Holloway::instance();

        // when
        $mapper = $holloway->getMapper(Pup::class);

        // then
        $this->assertInstanceOf(Mapper::class, $mapper);
    }

    /**
     * @test
     * @expectedException UnexpectedValueException
     */
    public function if_i_attempt_to_make_a_mapper_for_an_entity_that_does_not_have_a_map_registered_it_should_throw_an_exception()
    {
        // given
        $holloway = Holloway::instance();

        // when / then
        $mapper = $holloway->getMapper(User::class);
    }

    /** @test */
    public function the_mapper_method_should_never_create_more_than_one_copy_of_a_mapper()
    {
        // given
        $entityClassName = Pup::class;
        $holloway = Holloway::instance();

        // when
        $mapper1 = $holloway->getMapper($entityClassName);
        $mapper2 = $holloway->getMapper($entityClassName);

        // then
        $this->assertSame($mapper1, $mapper2);
    }
}
