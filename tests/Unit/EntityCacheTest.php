<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Holloway\EntityCache;

class EntityCacheTest extends TestCase
{
    /** @test */
    public function i_should_be_able_to_add_an_item_to_it()
    {
        // given
        $entityCache = new EntityCache('id');
        $record = ['id' => 1, 'pack_id' => 1, 'first_name' => 'Tobias', 'last_name' => 'Bennett'];

        // when
        $entityCache->add(1, $record);

        // then
        $this->assertSame($record, $entityCache->get(1));
    }

    /** @test */
    public function i_should_be_able_to_merge_many_items_into_it()
    {
        // given
        $entityCache = new EntityCache('id');

        $record1 = ['id' => 1, 'pack_id' => 1, 'first_name' => 'Tobias', 'last_name' => 'Bennett'];
        $record2 = ['id' => 2, 'pack_id' => 1, 'first_name' => 'Tyler', 'last_name' => 'Bennett'];
        $record3 = ['id' => 3, 'pack_id' => 1, 'first_name' => 'Tucker', 'last_name' => 'Bennett'];
        $alteredRecord1 = ['id' => 1, 'pack_id' => 1, 'first_name' => 'Tobi', 'last_name' => 'Bennett'];

        // when
        $entityCache->add(1, $record1);
        $entityCache->merge([$alteredRecord1, $record2, $record3]);
        $records = $entityCache->all();

        // then
        $this->assertCount(3, $records);
        $this->assertEquals($records[1]['first_name'], 'Tobi');
    }

    /** @test */
    public function i_should_be_able_to_ask_it_if_it_has_an_item()
    {
        // given
        $entityCache = new EntityCache('id');

        // when
        $entityCache->add(1, ['id' => 1, 'pack_id' => 1, 'first_name' => 'Tobias', 'last_name' => 'Bennett']);

        // then
        $this->assertTrue($entityCache->has(1));
    }

     /** @test */
    public function i_should_be_able_to_flush_all_items_from_it()
    {
        // given
        $entityCache = new EntityCache('id');

        // when
        $entityCache->add(1, ['id' => 1, 'pack_id' => 1, 'first_name' => 'Tobias', 'last_name' => 'Bennett']);
        $entityCache->flush();

        // then
        $this->assertCount(0, $entityCache->all());
    }
}