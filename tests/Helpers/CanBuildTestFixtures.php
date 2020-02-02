<?php

namespace CodeSleeve\Holloway\Tests\Helpers;

use CodeSleeve\Holloway\Holloway;
use CodeSleeve\Holloway\Mapper;
use Illuminate\Database\Capsule\Manager as Capsule;

trait CanBuildTestFixtures
{
    /**
     * @return void
     */
    public function setUp() : void
    {
        static $eventManager;

        if (!$eventManager) {
            $eventManager = new \Illuminate\Events\Dispatcher;
        }

        Mapper::setEventManager($eventManager);
        Capsule::beginTransaction();
    }

    /**
     * @return void
     */
    public function tearDown() : void
    {
        Capsule::rollBack();
        Holloway::instance()->flushEntityCache();
        Mapper::unsetEventManager();
    }

    /**
     * @return void
     */
    protected function buildFixtures()
    {
        Capsule::table('companies')->insert([
            ['id' => 1, 'name' => 'Diamond Pet Foods and Accessories', 'founded_at' => '2015-10-05', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['id' => 2, 'name' => 'Blue Pet Products', 'founded_at' => '2012-11-27', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]
        ]);

        Capsule::table('pup_foods')->insert([
            ['id' => 1, 'company_id' => 1, 'name' => '4Health', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['id' => 2, 'company_id' => 1, 'name' => 'Taste of The Wild', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['id' => 3, 'company_id' => 2, 'name' => 'Blue Buffalo', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]
        ]);

        Capsule::table('users')->insert([
            ['id' => 1, 'first_name' => 'Travis', 'last_name' => 'Bennett', 'email' => 'tandrewbennet@hotmail.com', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['id' => 2, 'first_name' => 'Marilyn', 'last_name' => 'Bennett', 'email' => 'marilynt85@yahoo.com', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]
        ]);

        Capsule::table('packs')->insert([
            ['id' => 1, 'name' => 'Bennett Pack', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['id' => 2, 'name' => 'Adams Pack', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]
        ]);

        Capsule::table('pups')->insert([
            ['id' => 1, 'pack_id' => 1, 'first_name' => 'Tobias', 'last_name'  => 'Bennett', 'coat' => 'black', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['id' => 2, 'pack_id' => 1, 'first_name' => 'Tyler', 'last_name'   => 'Bennett', 'coat' => 'black', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['id' => 3, 'pack_id' => 1, 'first_name' => 'Tucker', 'last_name'  => 'Bennett', 'coat' => 'black', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['id' => 4, 'pack_id' => 1, 'first_name' => 'Trinka', 'last_name'  => 'Bennett', 'coat' => 'brown', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['id' => 5, 'pack_id' => 2, 'first_name' => 'Lucky', 'last_name'   => 'Adams', 'coat' => 'white', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['id' => 6, 'pack_id' => 2, 'first_name' => 'Duchess', 'last_name' => 'Adams', 'coat' => 'mixed', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]
        ]);

        Capsule::table('collars')->insert([
            ['id' => 1, 'pup_id' => 1, 'company_id' => 1, 'color' => 'black', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['id' => 2, 'pup_id' => 2, 'company_id' => 1, 'color' => 'blue', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['id' => 3, 'pup_id' => 3, 'company_id' => 1, 'color' => 'red', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['id' => 4, 'pup_id' => 4, 'company_id' => 1, 'color' => 'leopard print', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['id' => 5, 'pup_id' => 5, 'company_id' => 1, 'color' => 'orange', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['id' => 6, 'pup_id' => 6, 'company_id' => 1, 'color' => 'orange', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]
        ]);

        Capsule::table('pups_users')->insert([
            // Travis
            ['pup_id' => 1, 'user_id' => 1],
            ['pup_id' => 2, 'user_id' => 1],
            ['pup_id' => 3, 'user_id' => 1],
            ['pup_id' => 4, 'user_id' => 1],
            ['pup_id' => 5, 'user_id' => 1],

            // Marilyn
            ['pup_id' => 1, 'user_id' => 2],
            ['pup_id' => 2, 'user_id' => 2],
            ['pup_id' => 3, 'user_id' => 2],
            ['pup_id' => 4, 'user_id' => 2],
            ['pup_id' => 5, 'user_id' => 2],
        ]);

        Capsule::table('surrogate_pups_users')->insert([
            // Travis
            ['pup_id' => 5, 'user_id' => 1],
            ['pup_id' => 6, 'user_id' => 1],

            // Marilyn
            ['pup_id' => 5, 'user_id' => 2],
            ['pup_id' => 6, 'user_id' => 2]
        ]);

        Capsule::table('pups_pup_foods')->insert([
            ['pup_id' => 1, 'pup_food_id' => 1],
            ['pup_id' => 1, 'pup_food_id' => 2],

            ['pup_id' => 2, 'pup_food_id' => 1],
            ['pup_id' => 2, 'pup_food_id' => 2],

            ['pup_id' => 3, 'pup_food_id' => 1],
            ['pup_id' => 3, 'pup_food_id' => 2],

            ['pup_id' => 4, 'pup_food_id' => 1],
            ['pup_id' => 4, 'pup_food_id' => 2]
        ]);
    }
}