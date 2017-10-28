<?php

namespace Tests\Integration;

use Carbon\Carbon;
use Holloway\Relationships\{HasOne, BelongsTo, HasMany, BelongsToMany};
use Holloway\{Holloway, Entity, Mapper};
use Illuminate\Contracts\Pagination;
use Tests\Fixtures\Entities\{User, Pup, PupFood, Collar, Pack, Company};
use Tests\Fixtures\Mappers\{UserMapper, PupMapper, PupFoodMapper, CollarMapper, PackMapper, CompanyMapper};
use Tests\Helpers\CanBuildTestFixtures;

class MapperTest extends TestCase
{
    use CanBuildTestFixtures;

    /**
     * @return  void
     */
    public static function setUpBeforeClass()
    {
        // Set up our Holloway instance and register our fixture mappers.
        Holloway::instance()->register([
            'Tests\Fixtures\Mappers\CollarMapper',
            'Tests\Fixtures\Mappers\CompanyMapper',
            'Tests\Fixtures\Mappers\PackMapper',
            'Tests\Fixtures\Mappers\PupFoodMapper',
            'Tests\Fixtures\Mappers\PupMapper',
            'Tests\Fixtures\Mappers\UserMapper',
        ]);
    }

    /**
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();

        Holloway::instance()->flushEntityCache();
    }


    /** @test **/
    public function it_should_be_able_to_return_the_name_of_the_table_that_it_uses()
    {
        // given
        $mapper = new PupMapper;

        // when
        $tableName = $mapper->getTableName();

        // then
        $this->assertEquals('pups', $tableName);
    }

    /** @test */
    public function it_should_be_able_to_retrieve_a_has_one_relationship_that_has_been_defined_on_it()
    {
        // given
        $this->buildFixtures();
        $holloway = Holloway::instance();

        $pupMapper = $holloway->getMapper(Pup::class);

        // when
        $hasOneRelationship = $pupMapper->getRelationship('collar');

        // then
        $this->assertInstanceOf(HasOne::class, $hasOneRelationship);
    }

    /** @test */
    public function i_should_be_able_to_retrieve_a_belongs_to_relationship_that_has_been_defined_on_it()
    {
        // given
        $this->buildFixtures();
        $holloway = Holloway::instance();

        $collarMapper = $holloway->getMapper(Collar::class);

        // when
        $belongsToRelationship = $collarMapper->getRelationship('pup');

        // then
        $this->assertInstanceOf(BelongsTo::class, $belongsToRelationship);
    }

    /** @test */
    public function i_should_be_able_to_retreive_a_has_many_relationship_that_has_been_defined_on_it()
    {
        // given
        $this->buildFixtures();
        $holloway = Holloway::instance();

        $packMapper = $holloway->getMapper(Pack::class);

        // when
        $hasManyRelationship = $packMapper->getRelationship('pups');

        // then
        $this->assertInstanceOf(HasMany::class, $hasManyRelationship);
    }

    /** @test */
    public function it_should_be_able_to_retreive_a_belongs_to_many_relationship_that_has_been_defined_on_it()
    {
        // given
        $this->buildFixtures();
        $holloway = Holloway::instance();

        $userMapper = $holloway->getMapper(User::class);

        // when
        $belongsToManyRelationship = $userMapper->getRelationship('pups');

        // then
        $this->assertInstanceOf(BelongsToMany::class, $belongsToManyRelationship);
    }

    /** @test */
    public function it_should_be_able_to_define_relationships_even_if_the_local_and_foreign_key_names_and_table_names_are_not_given()
    {
        // given
        $this->buildFixtures();
        $holloway = Holloway::instance();

        $companyMapper = $holloway->getMapper(Company::class);

        // when
        $hasManyRelationship = $companyMapper->getRelationship('pupFoods');

        // then
        $this->assertInstanceOf(HasMany::class, $hasManyRelationship);
    }









    /** @test */
    public function if_no_results_are_returned_from_a_query_then_it_wont_attempt_to_map_an_entity()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(PupFood::class);

        // when
        $pupFood = $mapper->find(4);    // There is no record with this id seeded into our fixture.

        // then
        $this->assertNull($pupFood);
    }

    /** @test */
    public function it_can_map_an_entity_from_a_find_operation()
    {
        // given
        $this->buildFixtures();
        $entityClassName = PupFood::class;
        $mapper = Holloway::instance()->getMapper($entityClassName);

        // when
        $pupFood = $mapper->find(1);

        // then
        $this->assertInstanceOf($entityClassName, $pupFood);
        $this->assertEquals(1, $pupFood->id());
    }

    /** @test */
    public function it_can_map_a_collection_of_entities_from_an_all_operation()
    {
        // given
        $this->buildFixtures();
        $entityClassName = Pup::class;
        $mapper = Holloway::instance()->getMapper($entityClassName);

        // when
        $pups = $mapper->all();

        // then
        $this->assertEquals(6, $pups->count());

        $pups->each(function($pup) {
            $this->assertInstanceOf(Pup::class, $pup);
        });
    }

    /** @test */
    public function the_paginate_method_should_return_a_paginated_collection_of_mapped_entities()
    {
        // given
        $this->buildFixtures();
        $entityClassName = Pup::class;
        $mapper = Holloway::instance()->getMapper($entityClassName);

        // when
        $pups = $mapper->paginate(2);

        // then
        $this->assertInstanceOf('Illuminate\Contracts\Pagination\Paginator', $pups);
        $this->assertCount(2, $pups->items());
        $pups->each(function($pup) {
            $this->assertInstanceOf(Pup::class, $pup);
        });
    }

    /** @test */
    public function it_can_load_nested_relationships()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(Pack::class);

        // when
        $pack = $mapper->with('pups.collar')->find(1);

        // then
        $pack->pups()->each(function($pup) {
            $this->assertInstanceOf(Collar::class, $pup->collar());
        });
    }

    /** @test */
    public function it_can_load_nested_relationships_onto_many_entities()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(Pack::class);

        // when
        $packs = $mapper->with('pups.collar')->get();

        // then
        $packs->each(function($pack) {
            $this->assertInstanceOf(Pack::class, $pack);

            $pack->pups()->each(function($pup) {
                $this->assertInstanceOf(Collar::class, $pup->collar());
            });
        });
    }

    /** @test */
    public function it_can_load_a_has_one_relationship_onto_an_entity()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(Pup::class);

        // when
        $pup = $mapper->with('collar')->find(1);

        // then
        $this->assertInstanceOf(Collar::class, $pup->collar());
    }

    /** @test */
    public function it_can_load_a_has_one_relationship_onto_many_entities()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(Pup::class);

        // when
        $pups = $mapper->with('collar')->get();

        // then
        $pups->each(function($pup) {
            $this->assertInstanceOf(Collar::class, $pup->collar());
            $this->assertEquals($pup->collar()->pupId(), $pup->id());
        });
    }

    /** @test */
    public function it_can_load_has_many_relationships_onto_an_entity()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(Pack::class);

        // when
        $pack = $mapper->with('pups')->find(1);

        // then
        $pack->pups->each(function($pup) use ($pack) {
            $this->assertInstanceOf(Pup::class, $pup);
            $this->assertEquals($pup->packId(), $pack->id());
        });
    }

    /** @test */
    public function it_can_load_has_many_relationships_onto_many_entities()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(Pack::class);

        // when
        $packs = $mapper->with('pups')->get();

        // then
        $packs->each(function($pack) {
            $pack->pups->each(function($pup) use ($pack) {
                $this->assertInstanceOf(Pup::class, $pup);
                $this->assertEquals($pup->packId(), $pack->id());
            });
        });
    }

    /** @test */
    public function it_can_load_belongs_to_relationships_onto_an_entity()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(Pup::class);

        // when
        $pup = $mapper->with('pack')->find(1);

        // then
        $this->assertInstanceOf(Pack::class, $pup->pack());
    }

    /** @test */
    public function it_can_load_belongs_to_relationships_onto_many_entities()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(Pup::class);

        // when
        $pups = $mapper->with('pack')->get();

        // then
        $pups->each(function($pup) {
            $this->assertInstanceOf(Pack::class, $pup->pack());
        });
    }

    /** @test */
    public function it_can_load_belongs_to_relationships_onto_an_entity_without_specifying_a_table_name_or_keys_within_the_relationship_definition()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(PupFood::class);

        // when
        $pupFood = $mapper->with('company')->find(1);

        // then
        $this->assertInstanceOf(Company::class, $pupFood->company());
    }

    /** @test */
    public function it_can_load_belongs_to_relationships_onto_many_entities_without_specifying_a_table_name_or_keys_within_the_relationship_definition()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(PupFood::class);

        // when
        $pupFoods = $mapper->with('company')->get();

        // then
        $pupFoods->each(function($pupFood) {
            $this->assertInstanceOf(Company::class, $pupFood->company());
        });
    }

    /** @test */
    public function it_can_load_belongs_to_many_relationships_onto_an_entity()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(User::class);

        // when
        $user = $mapper->with('pups')->find(1);

        // then
        $user->pups()->each(function($pup) use ($user) {
            $this->assertInstanceOf(Pup::class, $pup);
        });
    }

    /** @test */
    public function it_can_load_belongs_to_many_relationships_onto_many_entities()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(User::class);

        // when
        $users = $mapper->with('pups')->get();

        // then
        $users->each(function($user) {
            $user->pups()->each(function($pup) use ($user) {
                $this->assertInstanceOf(Pup::class, $pup);
            });
        });
    }

    /** @test */
    public function it_can_save_a_new_entity()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(Pup::class);

        // when
        $pup = new Pup(7, 2, 'Snowball', 'Adams');
        $mapper->store($pup);
        $snowball = $mapper->find(7);

        // then
        $this->assertInstanceOf(Pup::class, $snowball);
        $this->assertEquals('Snowball', $pup->firstName());
        $this->assertEquals('Adams', $pup->lastName());
    }

    /** @test */
    public function it_can_save_a_iterable_of_new_entities()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(Pup::class);

        // when
        $pup1 = new Pup(7, 2, 'Snowball', 'Adams');
        $pup2 = new Pup(8, 1, 'Moses', 'Bennett');

        $mapper->store([$pup1, $pup2]);

        $snowball = $mapper->find(7);
        $moses = $mapper->find(8);

        // then
        $this->assertInstanceOf(Pup::class, $snowball);
        $this->assertEquals('Snowball', $pup1->firstName());
        $this->assertEquals('Adams', $pup1->lastName());

        $this->assertInstanceOf(Pup::class, $moses);
        $this->assertEquals('Moses', $pup2->firstName());
        $this->assertEquals('Bennett', $pup2->lastName());
    }

    /** @test */
    public function it_can_update_an_existing_entity()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(Pup::class);

        // when
        $pup = $mapper->find(1);
        $pup->firstName('Toby');

        $mapper->store($pup);
        $tobias = $mapper->find(1);

        // then
        $this->assertInstanceOf(Pup::class, $tobias);
        $this->assertEquals('Toby', $pup->firstName());
    }

    /** @test */
    public function it_can_remove_an_existing_entity()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(Pup::class);
        $tobi = $mapper->find(1);

        // when
        $mapper->remove($tobi);
        $pups = $mapper->all();

        // then
        $this->assertCount(5, $pups);
    }

    /** @test */
    public function it_can_remove_an_iterable_of_existing_entities()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(Pup::class);
        $tobi = $mapper->find(1);

        // when
        $pups = $mapper->all();
        $mapper->remove($pups);

        // then
        $this->assertEquals(0, $mapper->count());
    }

    /** @test */
    public function it_can_soft_delete_entities_and_then_query_for_them_once_they_have_been_deleted()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(Pack::class);    // The pack mapper fixture uses soft deletes
        $bennettPack = $mapper->find(1);

        // when
        $mapper->remove($bennettPack);

        // then
        $this->assertCount(1, $mapper->all());
        $this->assertCount(2, $mapper->withTrashed()->get());
    }
}