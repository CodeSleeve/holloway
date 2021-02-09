<?php

namespace CodeSleeve\Holloway\Tests\Helpers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class MigrateFixtureTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public static function up()
    {
        if (!Schema::hasTable('companies')) {
            Schema::create('companies', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->date('founded_at');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('pup_foods')) {
            Schema::create('pup_foods', function (Blueprint $table) {
                $table->increments('id');

                $table->unsignedInteger('company_id');
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');

                $table->string('name');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->increments('id');
                $table->string('first_name');
                $table->string('last_name');
                $table->string('email')->unique();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('packs')) {
            Schema::create('packs', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
            });
        }

        if (!Schema::hasTable('pups')) {
            Schema::create('pups', function (Blueprint $table) {
                $table->increments('id');

                $table->unsignedInteger('pack_id');
                $table->foreign('pack_id')->references('id')->on('packs');

                $table->string('first_name');
                $table->string('last_name');
                $table->string('coat');
                $table->dateTime('deleted_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('collars')) {
            Schema::create('collars', function (Blueprint $table) {
                $table->increments('id');

                $table->unsignedInteger('pup_id');
                $table->foreign('pup_id')->references('id')->on('pups');

                $table->unsignedInteger('company_id');
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');

                $table->string('color');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('pups_users')) {
            Schema::create('pups_users', function (Blueprint $table) {
                $table->unsignedInteger('pup_id');
                $table->foreign('pup_id')->references('id')->on('pups')->onDelete('cascade');

                $table->unsignedInteger('user_id');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

                $table->primary(['pup_id', 'user_id']);
            });
        }

        if (!Schema::hasTable('surrogate_pups_users')) {
            Schema::create('surrogate_pups_users', function (Blueprint $table) {
                $table->unsignedInteger('pup_id');
                $table->foreign('pup_id')->references('id')->on('pups')->onDelete('cascade');

                $table->unsignedInteger('user_id');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

                $table->primary(['pup_id', 'user_id']);
            });
        }

        if (!Schema::hasTable('pups_pup_foods')) {
            Schema::create('pups_pup_foods', function (Blueprint $table) {
                $table->unsignedInteger('pup_id');
                $table->foreign('pup_id')->references('id')->on('pups')->onDelete('cascade');

                $table->unsignedInteger('pup_food_id');
                $table->foreign('pup_food_id')->references('id')->on('pup_foods')->onDelete('cascade');

                $table->primary(['pup_id', 'pup_food_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public static function down()
    {
        Schema::dropIfExists('companies');
        Schema::dropIfExists('pup_foods');
        Schema::dropIfExists('users');
        Schema::dropIfExists('packs');
        Schema::dropIfExists('pups');
        Schema::dropIfExists('collars');
        Schema::dropIfExists('pups_users');
        Schema::dropIfExists('pups_pup_foods');
    }
}