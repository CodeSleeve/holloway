<?php

use CodeSleeve\Holloway\{Mapper, SoftDeletingScope};
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;
use CodeSleeve\Holloway\Tests\Fixtures\Mappers\PupMapper;
use CodeSleeve\Holloway\Tests\Helpers\MigrateFixtureTables;

date_default_timezone_set('UTC');

$loader = require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/illuminate/support/helpers.php';

$container = new Container;
Facade::setFacadeApplication($container);
$capsule = new Capsule($container);
$container['db'] = $capsule->getDatabaseManager();

$capsule->addConnection([
    //'driver'    => 'pgsql',
    'driver'    => 'mysql',
    'host'      => 'localhost',
    'database'  => 'holloway',
    //'username'  => 'postgres',
    'username'  => 'root',
    'password'  => 'password',
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => '',
]);

// Make this Capsule instance available globally via static methods
$capsule->setAsGlobal();

// Migrate our fixture DB tables.
MigrateFixtureTables::up();

// Set the default connection and the test EventManager
Mapper::setConnectionResolver($capsule->getDatabaseManager());
Mapper::setEventManager(new Dispatcher);

// Add the soft deleting scope to the pup mapper
PupMapper::addGlobalScope(new SoftDeletingScope);