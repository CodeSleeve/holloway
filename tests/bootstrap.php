<?php

use CodeSleeve\Holloway\{Mapper, SoftDeletingScope};
use CodeSleeve\Holloway\Tests\Fixtures\Mappers\CollarMapper;
use CodeSleeve\Holloway\Tests\Fixtures\Mappers\PupFoodMapper;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;
use CodeSleeve\Holloway\Tests\Fixtures\Mappers\PupMapper;
use CodeSleeve\Holloway\Tests\Helpers\MigrateFixtureTables;

date_default_timezone_set('UTC');

$loader = require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/illuminate/support/helpers.php';

// Setup the container and the capsule
$container = new Container;
Facade::setFacadeApplication($container);
$capsule = new Capsule($container);

// Bind the db connection to the container
$container['db'] = $capsule->getDatabaseManager();

// Bind the db.schema builder to the container
$container->bind('db.schema', function ($app) {
    return $app['db']->connection()->getSchemaBuilder();
});

// Setup a postgres connection
$capsule->addConnection([
    'driver'    => 'pgsql',
    'host'      => 'localhost',
    'database'  => 'holloway_test',
    'username'  => 'postgres',
    'password'  => 'password',
    'charset'   => 'utf8',
    'prefix'    => '',
    'prefix_indexes' => true,
    'search_path' => 'public',
    'sslmode' => 'prefer',
]);

// set up a mysql connection
// $capsule->addConnection([
//     'driver'    => 'mysql',
//     'host'      => 'localhost',
//     'database'  => 'holloway_test',
//     'username'  => 'root',
//     'password'  => 'password',
//     'charset'   => 'utf8',
//     'collation' => 'utf8_unicode_ci',
//     'prefix'    => '',
//     'schema'    => 'public',
// ]);

// Make this Capsule instance available globally via static methods
$capsule->setAsGlobal();

// Migrate our fixture DB tables.
MigrateFixtureTables::up();

// Set the default connection and the test EventManager
Mapper::setConnectionResolver($capsule->getDatabaseManager());
Mapper::setEventManager(new Dispatcher);

// Add the soft deleting scopes to a few of the mappers.
CollarMapper::addGlobalScope(new SoftDeletingScope);
PupMapper::addGlobalScope(new SoftDeletingScope);
PupFoodMapper::addGlobalScope(new SoftDeletingScope);