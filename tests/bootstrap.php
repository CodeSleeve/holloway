<?php

use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Illuminate\Database\Capsule\Manager as Capsule;
use CodeSleeve\Holloway\{Mapper, SoftDeletingScope};
use CodeSleeve\Holloway\Tests\Fixtures\Mappers\PupMapper;
use CodeSleeve\Holloway\Tests\Helpers\MigrateFixtureTables;
use CodeSleeve\Holloway\Tests\Fixtures\Mappers\CollarMapper;
use CodeSleeve\Holloway\Tests\Fixtures\Mappers\PupFoodMapper;

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
    $dotenv = Dotenv\Dotenv::createUnsafeImmutable(__DIR__ . '/../');
    $dotenv->load();
}

// Get database configuration from environment
$dbDriver = getenv('DB_DRIVER');
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbDatabase = getenv('DB_DATABASE') ?: 'holloway_test';
$dbUsername = getenv('DB_USERNAME');
$dbPassword = getenv('DB_PASSWORD') ?: 'password';

// Setup database connection based on driver
if ($dbDriver === 'mysql') {
    $capsule->addConnection([
        'driver'    => 'mysql',
        'host'      => $dbHost,
        'database'  => $dbDatabase,
        'username'  => $dbUsername ?: 'root',
        'password'  => $dbPassword,
        'charset'   => 'utf8',
        'collation' => 'utf8_unicode_ci',
        'prefix'    => '',
        'schema'    => 'public',
        'engine'    => 'InnoDB',
        'strict'    => true,
    ]);
} else if ($dbDriver === 'pgsql') {
    $capsule->addConnection([
        'driver'    => 'pgsql',
        'host'      => $dbHost,
        'database'  => $dbDatabase,
        'username'  => $dbUsername ?: 'postgres',
        'password'  => $dbPassword,
        'charset'   => 'utf8',
        'prefix'    => '',
        'prefix_indexes' => true,
        'search_path' => 'public',
        'sslmode' => 'prefer',
    ]);
}
// sqlite
else if ($dbDriver === 'sqlite') {
    $capsule->addConnection([
        'driver'    => 'sqlite',
        'database'  => $dbDatabase ?: ':memory:',
        'prefix'    => '',
    ]);
} else {
    throw new Exception('No suitable database driver specified. Set DB_DRIVER environment variable to mysql, pgsql, or sqlite.');
}

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