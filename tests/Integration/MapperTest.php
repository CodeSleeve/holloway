<?php

namespace CodeSleeve\Holloway\Tests\Integration;

use Carbon\CarbonImmutable;
use CodeSleeve\Holloway\Relationships\{HasOne, BelongsTo, HasMany, BelongsToMany, Custom};
use CodeSleeve\Holloway\Holloway;
use CodeSleeve\Holloway\SoftDeletingScope;
use CodeSleeve\Holloway\Tests\Fixtures\Entities\{User, Pup, PupFood, Collar, Pack, Company};
use CodeSleeve\Holloway\Tests\Fixtures\Mappers\PupMapper;
use CodeSleeve\Holloway\Tests\Helpers\CanBuildTestFixtures;
use Mockery as m;

class MapperTest extends TestCase
{
    use CanBuildTestFixtures;

    /**
     * @return  void
     */
    public static function setUpBeforeClass(): void
    {
        // Set up our Holloway instance and register our fixture mappers.
        Holloway::instance()->register([
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\CollarMapper',
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\CompanyMapper',
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\PackMapper',
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\PupFoodMapper',
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\PupMapper',
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\UserMapper',
        ]);
    }

    /** @test **/
    public function it_should_be_able_to_return_the_name_of_the_table_that_it_uses()
    {
        // given
        $mapper = new PupMapper;

        // when
        $table = $mapper->getTable();

        // then
        $this->assertEquals('pups', $table);
    }

    /** @test */
    public function it_should_be_able_to_retrieve_a_custom_relationship_that_has_been_defined_on_it()
    {
        // given
        $this->buildFixtures();
        $holloway = Holloway::instance();

        $packMapper = $holloway->getMapper(Pack::class);

        // when
        $customRelationship = $packMapper->getRelationship('collars');

        // then
        $this->assertInstanceOf(Custom::class, $customRelationship);
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
    public function ii_should_be_able_to_retrieve_a_belongs_to_relationship_that_has_been_defined_on_it()
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
    public function ii_should_be_able_to_retreive_a_has_many_relationship_that_has_been_defined_on_it()
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
        $this->assertEquals(1, $pupFood->id);
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
    public function it_can_load_nested_relationships_that_have_a_null_value()
    {
        // given
        $this->buildFixtures();
        $packMapper = Holloway::instance()->getMapper(Pack::class);
        $pupMapper = Holloway::instance()->getMapper(Pup::class);

        // when
        $bennettPack = $packMapper->find(1);
        $pup = new Pup($bennettPack, 'Moses', 'Bennett', 'white');
        $pupMapper->store($pup);
        $pack = $packMapper->with('pups.collar')->find(1);

        // then
        $moses = $pack->pups
            ->filter(fn($pup) => $pup->first_name == 'Moses')
            ->first();

        $this->assertInstanceOf(Pup::class, $moses);
        $this->assertNull($moses->collar);
    }

    /** @test */
    public function it_can_load_a_nested_relationship()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(Pack::class);

        // when
        $pack = $mapper->with('pups.collar')->find(1);

        // then
        $pack->pups->each(function($pup) {
            $this->assertInstanceOf(Pup::class, $pup);
            $this->assertInstanceOf(Collar::class, $pup->collar);
        });
    }

    /** @test */
    public function it_can_load_a_nested_relationship_onto_many_entities()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(Pack::class);

        // when
        $packs = $mapper->with('pups.collar')->get();

        // then
        $packs->each(function($pack) {
            $this->assertInstanceOf(Pack::class, $pack);

            $pack->pups->each(function($pup) {
                $this->assertInstanceOf(Collar::class, $pup->collar);
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
        $this->assertInstanceOf(Collar::class, $pup->collar);
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
            $this->assertInstanceOf(Collar::class, $pup->collar);
            $this->assertEquals($pup->collar->pup_id, $pup->id);
        });
    }

    /** @test */
    public function it_can_load_a_has_many_relationship_onto_an_entity()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(Pack::class);

        // when
        $pack = $mapper->with('pups')->find(1);

        // then
        $pack->pups->each(function($pup) use ($pack) {
            $this->assertInstanceOf(Pup::class, $pup);
            $this->assertEquals($pup->pack_id, $pack->id);
        });
    }

    /** @test */
    public function it_can_load_a_has_many_relationship_onto_many_entities()
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
                $this->assertEquals($pup->pack_id, $pack->id);
            });
        });
    }

    /** @test */
    public function it_can_load_a_belongs_to_relationship_onto_an_entity()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(Pup::class);

        // when
        $pup = $mapper->with('pack')->find(1);

        // then
        $this->assertInstanceOf(Pack::class, $pup->pack);
    }

    /** @test */
    public function it_can_load_a_belongs_to_relationship_onto_many_entities()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(Pup::class);

        // when
        $pups = $mapper->with('pack')->get();

        // then
        $pups->each(function($pup) {
            $this->assertInstanceOf(Pack::class, $pup->pack);
        });
    }

    /** @test */
    public function it_can_load_the_same_relationship_from_multiple_paths_at_the_same_time()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(User::class);

        // when
        $travis = $mapper->with('pups.collar', 'surrogatePups.collar')->find(1);

        // then
        $this->assertCount(5, $travis->pups);
        $this->assertCount(2, $travis->surrogatePups);
        $this->assertEquals('black', $travis->pups->where('first_name', 'Tobias')->first()->collar->color);
        $this->assertEquals('red', $travis->pups->where('first_name', 'Tucker')->first()->collar->color);
        $this->assertEquals('blue', $travis->pups->where('first_name', 'Tyler')->first()->collar->color);
        $this->assertEquals('leopard print', $travis->pups->where('first_name', 'Trinka')->first()->collar->color);

        $travis->surrogatePups->each(function($pup) {
            $this->assertEquals('orange', $pup->collar->color);
        });
    }

    /** @test */
    public function it_can_load_a_custom_relationship_onto_an_entity()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(Pack::class);

        // when
        $pack = $mapper->with('collars')->first();

        // then
        $this->assertInstanceOf(Collar::class, $pack->collars->first());
        $this->assertCount(4, $pack->collars);
    }

    /** @test */
    public function it_can_load_a_custom_relationship_onto_many_entities()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(Pack::class);

        // when
        $pack = $mapper->with('collars')->get();

        // then
        $pack->each(function($pack) {
            $pack->collars->each(function($collar) {
                $this->assertInstanceOf(Collar::class, $collar);
            });
        });
    }

    /** @test */
    public function it_can_load_a_belongs_to_relationship_onto_an_entity_without_specifying_a_table_name_or_keys_within_the_relationship_definition()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(PupFood::class);

        // when
        $pupFood = $mapper->with('company')->find(1);

        // then
        $this->assertInstanceOf(Company::class, $pupFood->company);
    }

    /** @test */
    public function it_can_load_a_belongs_to_relationship_onto_many_entities_without_specifying_a_table_name_or_keys_within_the_relationship_definition()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(PupFood::class);

        // when
        $pupFoods = $mapper->with('company')->get();

        // then
        $pupFoods->each(function($pupFood) {
            $this->assertInstanceOf(Company::class, $pupFood->company);
        });
    }

    /** @test */
    public function it_can_load_a_belongs_to_many_relationship_onto_an_entity()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(User::class);

        // when
        $user = $mapper->with('pups')->find(1);

        // then
        $user->pups->each(function($pup) {
            $this->assertInstanceOf(Pup::class, $pup);
        });
    }

    /** @test */
    public function it_can_load_a_belongs_to_many_relationship_onto_many_entities()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(User::class);

        // when
        $users = $mapper->with('pups')->get();

        // then
        $users->each(function($user) {
            $user->pups->each(function($pup) {
                $this->assertInstanceOf(Pup::class, $pup);
            });
        });
    }

    /** @test */
    public function it_can_chunk_relationships_and_clear_the_entity_cache_after_each_chunk()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(Pup::class);

        // when
        $mapper->with('collar')
            ->orderBy('created_at')
            ->chunk(2, function($pups) {
                $pups->each(function($pup) {
                    $this->assertInstanceOf(Collar::class, $pup->collar);
                });
            });

        // then
        $this->assertEquals(0, $mapper->getNumberOfCachedEntities());
    }

    /** @test */
    public function it_can_chunk_relationships_by_id_and_clear_the_entity_cache_after_each_chunk()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(Pup::class);

        // when
        $mapper->with('collar')
            ->chunkById(2, function($pups) {
                $pups->each(function($pup) {
                    $this->assertInstanceOf(Collar::class, $pup->collar);
                });
            });

        // then
        $this->assertEquals(0, $mapper->getNumberOfCachedEntities());
    }

    /** @test */
    public function it_can_save_a_new_entity_and_set_timestamps_on_it()
    {
        // given
        $this->buildFixtures();
        $pupMapper = Holloway::instance()->getMapper(Pup::class);
        $packMapper = Holloway::instance()->getMapper(Pack::class);
        $adamsPack = $packMapper->find(2);

        // when
        $pup = new Pup($adamsPack, 'Snowball', 'Adams', 'white');
        $pupMapper->store($pup);
        $snowball = $pupMapper->where('first_name', 'Snowball')->first();

        // then
        $this->assertInstanceOf(Pup::class, $snowball);
        $this->assertEquals('Snowball', $pup->first_name);
        $this->assertEquals('Adams', $pup->last_name);
        $this->assertInstanceOf(\Carbon\CarbonImmutable::class, $snowball->created_at);
        $this->assertInstanceOf(\Carbon\CarbonImmutable::class, $snowball->updated_at);
    }

    /** @test */
    public function it_can_save_a_new_entity_without_setting_timestamps_on_it()
    {
       // given
       $this->buildFixtures();
       $packMapper = Holloway::instance()->getMapper(Pack::class);

       // when
       $pack = new Pack('new pack');
       $packMapper->store($pack);
       $newPack = $packMapper->find($pack->id);

       // then
       $this->assertInstanceOf(Pack::class, $newPack);
       $this->assertEquals('new pack', $newPack->name);
       $this->assertNull($newPack->created_at);
       $this->assertNull($newPack->updated_at);
    }

    /** @test */
    public function it_can_save_a_iterable_of_new_entities_and_set_timestamps_on_them()
    {
        // given
        $this->buildFixtures();
        $pupMapper = Holloway::instance()->getMapper(Pup::class);
        $packMapper = Holloway::instance()->getMapper(Pack::class);
        $bennettPack = $packMapper->find(1);
        $adamsPack = $packMapper->find(2);

        // when
        $pup1 = new Pup($adamsPack, 'Snowball', 'Adams', 'white');
        $pup2 = new Pup($bennettPack, 'Moses', 'Bennett', 'white');

        $pupMapper->store([$pup1, $pup2]);

        $snowball = $pupMapper->where('first_name', 'Snowball')->first();
        $moses = $pupMapper->where('first_name', 'Moses')->first();

        // then
        $this->assertInstanceOf(Pup::class, $snowball);
        $this->assertEquals('Snowball', $snowball->first_name);
        $this->assertEquals('Adams', $snowball->last_name);
        $this->assertInstanceOf(\Carbon\CarbonImmutable::class, $snowball->created_at);
        $this->assertInstanceOf(\Carbon\CarbonImmutable::class, $snowball->updated_at);

        $this->assertInstanceOf(Pup::class, $moses);
        $this->assertEquals('Moses', $moses->first_name);
        $this->assertEquals('Bennett', $moses->last_name);
        $this->assertInstanceOf(\Carbon\CarbonImmutable::class, $moses->created_at);
        $this->assertInstanceOf(\Carbon\CarbonImmutable::class, $moses->updated_at);
    }

    /** @test */
    public function it_can_update_an_existing_entity_and_dispatch_storing_updating_updated_and_stored_persistence_events_when_doing_so()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(Pup::class);

        $tobi = $mapper->find(1);
        $tobi->setFirstName('Toby');

        $mockDispatcher = m::mock('Illuminate\Contracts\Events\Dispatcher');
        $mockDispatcher->shouldReceive('dispatch')->once()->with('storing: CodeSleeve\Holloway\Tests\Fixtures\Entities\Pup', $tobi);
        $mockDispatcher->shouldReceive('dispatch')->once()->with('updating: CodeSleeve\Holloway\Tests\Fixtures\Entities\Pup', $tobi);
        $mockDispatcher->shouldReceive('dispatch')->once()->with('updated: CodeSleeve\Holloway\Tests\Fixtures\Entities\Pup', $tobi);
        $mockDispatcher->shouldReceive('dispatch')->once()->with('stored: CodeSleeve\Holloway\Tests\Fixtures\Entities\Pup', $tobi);
        $mapper->setEventManager($mockDispatcher);

        // when
        $mapper->store($tobi);
        $tobias = $mapper->find(1);

        // then
        $this->assertInstanceOf(Pup::class, $tobias);
        $this->assertEquals('Toby', $tobias->first_name);
    }

    /** @test */
    public function it_will_update_the_updated_at_column_and_ignore_changes_to_the_created_at_column_when_an_entity_with_timestamps_is_updated()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(Pup::class);

        $tobi = $mapper->find(1);
        $previousUpdatedAtValue = $tobi->updated_at;
        $previousCreatedAtValue = $tobi->created_at;

        // when
        sleep(1);                                       // Create artificial delay so that our seeded data is 1 second behind the test code below.
        $tobi->setFirstName('Toby');
        $tobi->setCreatedAt(CarbonImmutable::now());    // Changes to the timestamp properties should be ignored by the mapper.
        $mapper->store($tobi);
        $tobias = \Illuminate\Database\Capsule\Manager::table('pups')->find(1);

        // then
        $this->assertNotEquals($previousUpdatedAtValue->toDateTimeString(), $tobias->updated_at);     // The updated_at column should have a new timestamp value.
        $this->assertEquals($previousCreatedAtValue->toDateTimeString(), $tobias->created_at);        // The created_at column should not have changed.
        $this->assertEquals($tobi->updated_at->toDateTimeString(), $tobias->updated_at);              // The Entity updated_at property should now have the new CarbonImmutable value set by the mapper fixture after the record was upated.
    }

    /** @test */
    public function it_can_remove_an_existing_entity_and_dispatch_removing_and_removed_persistence_events_when_doing_so()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(Pup::class);
        $tobi = $mapper->find(1);

        $mockDispatcher = m::mock('Illuminate\Contracts\Events\Dispatcher');
        $mockDispatcher->shouldReceive('dispatch')->once()->with('removing: CodeSleeve\Holloway\Tests\Fixtures\Entities\Pup', $tobi);
        $mockDispatcher->shouldReceive('dispatch')->once()->with('removed: CodeSleeve\Holloway\Tests\Fixtures\Entities\Pup', $tobi);

        $mapper->setEventManager($mockDispatcher);

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
        $mapper = Holloway::instance()->getMapper(Pup::class);    // The pup mapper fixture uses soft deletes
        $tobi = $mapper->find(1);

        // when
        $mapper->remove($tobi);

        // then
        $this->assertCount(5, $mapper->all());
        $this->assertCount(6, $mapper->withTrashed()->get());
    }

    /** @test */
    function it_doesnt_load_soft_deleted_entities_when_querying_relationships()
    {
        // given
        $this->buildFixtures();

        $pupMapper = Holloway::instance()->getMapper(Pup::class);    // The pup mapper fixture uses soft deletes
        $packMapper = Holloway::instance()->getMapper(Pack::class);

        // when
        $tobi = $pupMapper->find(1);
        $pupMapper->remove($tobi);
        $bennetPack = $packMapper->with('pups')->find(1);

        // then
        $this->assertCount(3, $bennetPack->pups);
        $this->assertCount(5, $pupMapper->get());
        $this->assertCount(6, $pupMapper->withTrashed()->get());
    }

    /** @test */
    function it_allows_soft_deleting_scopes_to_be_removed_when_querying_has_many_relationships()
    {
        // given
        $this->buildFixtures();

        $pupMapper = Holloway::instance()->getMapper(Pup::class);    // The pup mapper fixture uses soft deletes
        $packMapper = Holloway::instance()->getMapper(Pack::class);

        // when
        $tobi = $pupMapper->find(1);
        $pupMapper->remove($tobi);
        $bennetPack = $packMapper->with([
            'pups' => fn($query) => $query->withoutGlobalScope(SoftDeletingScope::class),
        ])
        ->find(1);

        // then
        $this->assertCount(4, $bennetPack->pups);
        $this->assertCount(5, $pupMapper->get());
    }

    /** @test */
    function it_allows_soft_deleting_scopes_to_be_removed_when_querying_has_one_relationships()
    {
        // given
        $this->buildFixtures();

        $pupMapper = Holloway::instance()->getMapper(Pup::class);    // The pup mapper fixture uses soft deletes
        $collarMapper = Holloway::instance()->getMapper(Collar::class);

        // when
        $tobisCollar = $collarMapper->find(1);
        $collarMapper->remove($tobisCollar);
        
        $tobi = $pupMapper->with([
            'collar' => fn($query) => $query->withoutGlobalScope(SoftDeletingScope::class),
        ])
        ->find(1);

        // then
        $this->assertInstanceOf(Collar::class, $tobi->collar);
        $this->assertCount(5, $collarMapper->get());
    }

    /** @test */
    function it_allows_soft_deleting_scopes_to_be_removed_when_querying_belongs_to_many_relationships()
    {
        // given
        $this->buildFixtures();

        $pupMapper = Holloway::instance()->getMapper(Pup::class);    // The pup mapper fixture uses soft deletes
        $pupFoodMapper = Holloway::instance()->getMapper(PupFood::class);

        // when
        $tasteOfTheWild = $pupFoodMapper->find(2);
        $pupFoodMapper->remove($tasteOfTheWild);
        
        $tobi = $pupMapper->with([
            'pupFoods' => fn($query) => $query->withoutGlobalScope(SoftDeletingScope::class),
        ])
        ->find(1);

        // then
        $this->assertInstanceOf(PupFood::class, $tobi->pupFoods[0]);
        $this->assertInstanceOf(PupFood::class, $tobi->pupFoods[1]);
        $this->assertCount(2, $pupFoodMapper->get());
    }

    /** @test */
    public function it_allows_end_users_to_creat_query_scopes()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(Collar::class);    // The collar mapper fixture has a query scope on it for orange colored collars.

        // when
        $orangeCollars = $mapper->thatAreOrange()->get();

        // then
        $this->assertCount(2, $orangeCollars);
    }

    /** @test */
    public function it_allows_end_users_to_creat_dynamic_query_scopes()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(Pup::class);    // The pup mapper fixture has a query scope on it for coat color.

        // when
        $blackPups = $mapper->ofCoat('black')->get();
        $whitePups = $mapper->ofCoat('white')->get();

        // then
        $this->assertCount(3, $blackPups);
        $this->assertCount(1, $whitePups);
    }

    /** @test */
    public function it_applies_global_query_scopes_when_querying_relations()
    {
        $this->markTestIncomplete('This test has not been implemented yet.');
    }

    /** @test */
    public function global_query_scopes_that_are_removed_will_no_longer_be_applied_when_querying_relations()
    {
        $this->markTestIncomplete('This test has not been implemented yet.');
    }
}