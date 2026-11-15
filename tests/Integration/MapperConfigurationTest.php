<?php

namespace CodeSleeve\Holloway\Tests\Integration;

use ReflectionProperty;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Collection;
use CodeSleeve\Holloway\{Builder, Holloway, Mapper};
use CodeSleeve\Holloway\Tests\Fixtures\Entities\{Pack, Pup};
use CodeSleeve\Holloway\Tests\Fixtures\Mappers\{PackWithoutTableMapper, PupMapper};
use CodeSleeve\Holloway\Tests\Helpers\CanBuildTestFixtures;

class MapperConfigurationTest extends TestCase
{
    use CanBuildTestFixtures;

    /**
     * @return  void
     */
    public static function setUpBeforeClass(): void
    {
        Holloway::instance()->register([
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\CollarMapper',
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\CompanyMapper',
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\PackMapper',
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\PupFoodMapper',
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\PupMapper',
            'CodeSleeve\Holloway\Tests\Fixtures\Mappers\UserMapper',
        ]);
    }

    /** @test */
    public function it_has_defaults_for_how_its_table_is_keyed()
    {
        // given
        $mapper = new PupMapper;

        // then
        $this->assertSame('id', $mapper->getKeyName());
        $this->assertSame('int', $mapper->getKeyType());
        $this->assertTrue($mapper->getIncrementing());
        $this->assertSame('id', $mapper->getQueueableId());
        $this->assertSame('pup_id', $mapper->getForeignKeyName());
        $this->assertSame('created_at', $mapper->getCreatedAtColumnName());
        $this->assertSame('updated_at', $mapper->getUpdatedAtColumnName());
    }

    /** @test */
    public function it_can_have_its_table_settings_changed()
    {
        // given
        $mapper = new PupMapper;

        // when
        $mapper->setKeyName('uuid');
        $mapper->setIncrementing(false);
        $mapper->setTable('puppies');
        $mapper->setConnection('other');

        // then
        $this->assertSame('uuid', $mapper->getKeyName());
        $this->assertFalse($mapper->getIncrementing());
        $this->assertSame('puppies', $mapper->getTable());
        $this->assertSame('other', $mapper->getConnectionName());
        $this->assertSame('uuid', $mapper->getQueueableId());
    }

    /** @test */
    public function it_works_out_its_table_from_its_entity_when_it_isnt_given_one()
    {
        // given
        $mapper = new PackWithoutTableMapper;

        // then
        $this->assertSame('packs', $mapper->getTable());
        $this->assertSame('pack_id', $mapper->getForeignKeyName());
    }

    /** @test */
    public function it_paginates_fifteen_to_a_page_unless_it_is_told_otherwise()
    {
        // given
        $mapper = new PupMapper;

        // when
        $returned = $mapper->setPerPage(25);

        // then
        $this->assertSame(25, $mapper->getPerPage());
        $this->assertSame($mapper, $returned);
        $this->assertSame(15, (new PupMapper)->getPerPage());
    }

    /** @test */
    public function it_returns_its_relationships_and_defaults()
    {
        // given
        $mapper = Holloway::instance()->getMapper(Pup::class);

        // then
        $this->assertSame(['collar', 'pack', 'pupFoods'], array_keys($mapper->getRelationships()));
        $this->assertSame([], $mapper->getWith());
    }

    /** @test */
    public function it_starts_a_query_explicitly_and_makes_collections()
    {
        // given
        $mapper = Holloway::instance()->getMapper(Pup::class);

        // then
        $this->assertInstanceOf(Builder::class, $mapper->query());
        $this->assertInstanceOf(Collection::class, $mapper->newCollection([1, 2]));
        $this->assertCount(2, $mapper->newCollection([1, 2]));
    }

    /** @test */
    public function it_inserts_an_entity_for_a_factory()
    {
        // given
        $this->buildFixtures();
        $pack = Holloway::instance()->getMapper(Pack::class)->find(1);
        $mapper = Holloway::instance()->getMapper(Pup::class);
        $pup = new Pup($pack, 'Moses', 'Bennett', 'white');

        // when
        $inserted = $mapper->factoryInsert($pup);

        // then
        $this->assertTrue($inserted);
        $this->assertSame('Moses', Capsule::table('pups')->where('id', $pup->id)->value('first_name'));
    }

    /** @test */
    public function it_inserts_an_entity_with_the_key_it_already_has_when_the_key_doesnt_increment()
    {
        // given
        $this->buildFixtures();
        $mapper = Holloway::instance()->getMapper(Pack::class);
        $pack = new Pack('Manual Pack');
        $pack->setId(10);

        // when
        $mapper->setIncrementing(false);

        try {
            $stored = $mapper->store($pack);
        } finally {
            $mapper->setIncrementing(true);
        }

        // then
        $this->assertTrue($stored);
        $this->assertSame('Manual Pack', Capsule::table('packs')->where('id', 10)->value('name'));
    }

    /** @test */
    public function it_can_have_its_connection_resolver_unset()
    {
        // given
        $resolver = Mapper::getConnectionResolver();
        $property = new ReflectionProperty(Mapper::class, 'resolver');
        $property->setAccessible(true);

        try {
            // when
            Mapper::unsetConnectionResolver();

            // then
            $this->assertNull($property->getValue());
        } finally {
            Mapper::setConnectionResolver($resolver);
        }

        $this->assertSame($resolver, Mapper::getConnectionResolver());
    }
}
