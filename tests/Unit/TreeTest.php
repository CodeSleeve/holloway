<?php

namespace Tests\Unit;

use Mockery as m;
use PHPUnit\Framework\TestCase;
use Holloway\Mapper;
use Holloway\Relationships\Tree;
use Holloway\Relationships\{HasMany, HasOne, BelongsToMany};
use Holloway\Holloway;
use Tests\Fixtures\Entities\{Pack, Pup, Collar, PupFood};
use Tests\Fixtures\Mappers\{PackMapper, PupMapper, CollarMapper, PupFoodMapper};

class TreeTest extends TestCase
{
    /** @test */
    public function it_should_be_able_to_compose_itself_from_a_single_load_string()
    {
        // given
        $holloway = Holloway::instance();

        /** Pups */
        $mockPupMapper = m::mock(PupMapper::class);

        /** Packs */
        $mockPupRelationship = m::mock(HasMany::class);
        $mockPupRelationship->shouldReceive('getEntityName')->once()->andReturn(Pup::class);

        $mockPackMapper = m::mock(PackMapper::class);
        $mockPackMapper->shouldReceive('getRelationship')->once()->with('pups')->andReturn($mockPupRelationship);

        $holloway->setMappers([
            Pack::class   => $mockPackMapper,
            Pup::class    => $mockPupMapper
        ]);

        $tree = new Tree($mockPackMapper);
        $tree->addLoads(['pups' => function(){}]);

        // when
        $tree->initialize();
        $actual = $tree->getData();

        $expected = [
            'pups' => [
                'name' => 'pups',
                'constraints' => function() {},
                'relationship' => $mockPupRelationship,
                'children' => []
            ]
        ];

        // then
        $this->assertEquals($expected, $actual);
    }

    /** @test */
    public function it_should_be_able_to_compose_itself_from_a_string_of_nested_loads()
    {
        // given
        $holloway = Holloway::instance();

        /** Collars */
        $mockCollarMapper = m::mock(CollarMapper::class);
        $mockCollarMapper->shouldReceive('getRelationship')->never();

        /** Pups */
        $mockCollarRelationship = m::mock(HasMany::class);
        $mockCollarRelationship->shouldReceive('getEntityName')->once()->andReturn(Collar::class);

        $mockPupMapper = m::mock(PupMapper::class);
        $mockPupMapper->shouldReceive('getRelationship')->once()->with('collar')->andReturn($mockCollarRelationship);

        /** Packs */
        $mockPupRelationship = m::mock(HasMany::class);
        $mockPupRelationship->shouldReceive('getEntityName')->once()->andReturn(Pup::class);

        $mockPackMapper = m::mock(PackMapper::class);
        $mockPackMapper->shouldReceive('getRelationship')->once()->with('pups')->andReturn($mockPupRelationship);

        $holloway->setMappers([
            Pack::class   => $mockPackMapper,
            Pup::class    => $mockPupMapper,
            Collar::class => $mockCollarMapper
        ]);

        $tree = new Tree($mockPackMapper);
        $tree->addLoads(['pups.collar' => function(){}]);

        // when
        $tree->initialize();
        $actual = $tree->getData();

        $expected = [
            'pups' => [
                'name' => 'pups',
                'constraints' => function() {},
                'relationship' => $mockPupRelationship,
                'children' => [
                    'collar' => [
                        'name' => 'collar',
                        'constraints' => function() {},
                        'relationship' => $mockCollarRelationship,
                        'children' => []
                    ]
                ]
            ]
        ];

        // then
        $this->assertEquals($expected, $actual);
    }

    /** @test */
    public function it_should_be_able_to_compose_itself_from_multiple_strings_of_nested_loads()
    {
        // given
        $holloway = Holloway::instance();

        /** Pup Foods */
        $mockPupFoodMapper = m::mock(PupFoodMapper::class);
        $mockPupFoodMapper->shouldReceive('getRelationship')->never();

        /** Collars */
        $mockCollarMapper = m::mock(CollarMapper::class);
        $mockCollarMapper->shouldReceive('getRelationship')->never();

        /** Pups */
        $mockCollarRelationship = m::mock(HasMany::class);
        $mockCollarRelationship->shouldReceive('getEntityName')->once()->andReturn(Collar::class);

        $mockPupFoodRelationship = m::mock(BelongsToMany::class);
        $mockPupFoodRelationship->shouldReceive('getEntityName')->once()->andReturn(PupFood::class);

        $mockPupMapper = m::mock(PupMapper::class);
        $mockPupMapper->shouldReceive('getRelationship')->once()->with('collar')->andReturn($mockCollarRelationship);
        $mockPupMapper->shouldReceive('getRelationship')->once()->with('pupFoods')->andReturn($mockPupFoodRelationship);

        /** Packs */
        $mockPupRelationship = m::mock(HasMany::class);
        $mockPupRelationship->shouldReceive('getEntityName')->once()->andReturn(Pup::class);

        $mockPackMapper = m::mock(PackMapper::class);
        $mockPackMapper->shouldReceive('getRelationship')->once()->with('pups')->andReturn($mockPupRelationship);

        $holloway->setMappers([
            Pack::class    => $mockPackMapper,
            Pup::class     => $mockPupMapper,
            Collar::class  => $mockCollarMapper,
            PupFood::class => $mockPupFoodMapper
        ]);

        $tree = new Tree($mockPackMapper);
        $tree->addLoads([
            'pups.collar'   => function(){},
            'pups.pupFoods' => function(){}
        ]);

        // when
        $tree->initialize();
        $actual = $tree->getData();

        $expected = [
            'pups' => [
                'name' => 'pups',
                'constraints' => function() {},
                'relationship' => $mockPupRelationship,
                'children' => [
                    'collar' => [
                        'name' => 'collar',
                        'constraints' => function() {},
                        'relationship' => $mockCollarRelationship,
                        'children' => []
                    ],
                    'pupFoods' => [
                        'name' => 'pupFoods',
                        'constraints' => function() {},
                        'relationship' => $mockPupFoodRelationship,
                        'children' => []
                    ]
                ]
            ]
        ];

        // then
        $this->assertEquals($expected, $actual);
    }
}