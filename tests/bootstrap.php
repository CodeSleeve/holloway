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

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}

// Setup a postgres connection by default, but can be overriden easily with .env
$capsule->addConnection([
    'driver'    => $_ENV['DB_DRIVER'] ?? 'pgsql',
    'host'      => $_ENV['DB_HOST'] ?? 'localhost',
    'database'  => $_ENV['DB_DATABASE'] ?? 'holloway_test',
    'username'  => $_ENV['DB_USERNAME'] ?? 'postgres',
    'password'  => $_ENV['DB_PASSWORD'] ?? 'password',
    'charset'   => 'utf8',
    'prefix'    => '',
    'prefix_indexes' => true,
    'search_path' => 'public',
    'sslmode' => 'prefer',
]);


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