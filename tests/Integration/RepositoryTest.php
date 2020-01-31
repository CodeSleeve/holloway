<?php

namespace CodeSleeve\Tests\Holloway\Integration;

use CodeSleeve\Holloway\Holloway;
use CodeSleeve\Tests\Holloway\Fixtures\Entities\Pup;
use CodeSleeve\Tests\Holloway\Fixtures\Repositories\PupRepository;
use CodeSleeve\Tests\Holloway\Helpers\CanBuildTestFixtures;

class RepositoryTest extends TestCase
{
    use CanBuildTestFixtures;

    /**
     * @return  void
     */
    public static function setUpBeforeClass() : void
    {
        // Set up our Holloway instance and register our fixture mappers.
        Holloway::instance()->register([
            'CodeSleeve\Tests\Holloway\Fixtures\Mappers\CollarMapper',
            'CodeSleeve\Tests\Holloway\Fixtures\Mappers\CompanyMapper',
            'CodeSleeve\Tests\Holloway\Fixtures\Mappers\PackMapper',
            'CodeSleeve\Tests\Holloway\Fixtures\Mappers\PupFoodMapper',
            'CodeSleeve\Tests\Holloway\Fixtures\Mappers\PupMapper',
            'CodeSleeve\Tests\Holloway\Fixtures\Mappers\UserMapper',
        ]);
    }

    /** @test **/
    public function it_should_be_able_be_able_to_return_all_entities()
    {
        // given
        $this->buildFixtures();
        $repository = new PupRepository;

        // when
        $pups = $repository->all();

        // then
        $this->assertCount(6, $pups);
    }

    /** @test **/
    public function it_should_be_able_be_able_to_return_paginated_subset_of_entities()
    {
        // given
        $this->buildFixtures();
        $repository = new PupRepository;

        // when
        $pups = $repository->paginate(3);

        // then
        $this->assertCount(3, $pups);
    }

    /** @test **/
    public function it_should_be_able_be_able_to_find_entities_by_a_given_field()
    {
        // given
        $this->buildFixtures();
        $repository = new PupRepository;

        // when
        $pups = $repository->findBy('last_name', 'Bennett');

        // then
        $this->assertCount(4, $pups);
    }

    /** @test **/
    public function it_should_be_able_be_able_to_find_a_single_entity_by_a_given_field()
    {
        // given
        $this->buildFixtures();
        $repository = new PupRepository;

        // when
        $pup = $repository->findOneBy('first_name', 'Tobias');

        // then
        $this->assertEquals('Tobias', $pup->firstName());
    }

    /** @test **/
    public function it_should_be_able_to_remove_a_given_entity()
    {
        // given
        $this->buildFixtures();
        $repository = new PupRepository;

        // when
        $tobias = new Pup(1, 1, 'Tobias', 'Bennett', 'black');
        $repository->remove($tobias);
        $pups = $repository->all();

        // then
        $this->assertCount(5, $pups);
    }

}