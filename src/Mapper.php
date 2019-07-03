<?php

namespace CodeSleeve\Holloway;

use CodeSleeve\Holloway\Functions\Arr;
use Illuminate\Contracts\Events\Dispatcher as EventManagerInterface;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\{Connection, ConnectionResolverInterface as Resolver};
use Illuminate\Support\{Collection, Str};
use InvalidArgumentException;
use stdClass;
use Closure;
use DateTime;

abstract class Mapper
{
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /**
     * @var Resolver
     */
    protected static $resolver;

    /**
     * @var EventManagerInterface
     */
    protected static $eventManager;

    /**
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected static $dispatcher;

    /**
     * @var EntityCache
     */
    protected $entityCache;

    /**
     * @var string
     */
    protected $connection = '';

    /**
     * @var string
     */
    protected $tableName = '';

    /**
     * @var string
     */
    protected $primaryKeyName = 'id';

    /**
     * @var string
     */
    protected $keyType = 'int';

    /**
     * @var integer
     */
    protected $perPage = 15;

    /**
     * @var boolean
     */
    protected $hasTimestamps = true;

    /**
     * @var boolean
     */
    protected $isAutoIncrementing = true;

    /**
     * @var array
     */
    protected $relationships = [];

    /**
     * @var array
     */
    protected $with = [];

    /**
     * @var array
     */
    protected static $globalScopes = [];

    /**
     * Create a new mapper intance.
     */
    public function __construct()
    {
        $this->entityCache = new EntityCache($this->primaryKeyName);
    }

    /**
     * @param string $method
     * @param array  $parameters
     */
    public function __call(string $method , array $parameters)
    {
        return $this->newQuery()->$method(...$parameters);
    }

    /**
     * Return the name of the entity class for this map.
     *
     * @return string
     */
    abstract public function getEntityClassName() : string;

    /**
     * Return the identifier (primary key) for a given entity.
     *
     * @param  mixed $entity
     * @return mixed
     */
    abstract public function getIdentifier($entity);

    /**
     * Set the identifier (primary key) for a given entity.
     *
     * @param mixed $value
     * @param mixed $entity
     * @return void
     */
    abstract public function setIdentifier($entity, $value);

    /**
     * @param  stdClass   $record
     * @param  Collection $relationships
     * @return mixed
     */
    abstract public function hydrate(stdClass $record, Collection $relationships);

    /**
     * @param  mixed $entity
     * @return array
     */
    abstract public function dehydrate($entity) : array;

    /**
     * @return string
     */
    public function getCreatedAtColumnName() : string
    {
        return 'created_at';
    }

    /**
     * @return string
     */
    public function getUpdatedAtColumnName() : string
    {
        return 'updated_at';
    }

    /**
     * @param  string $name
     * @return Relationships\Relationship
     */
    public function getRelationship(string $name) : Relationships\Relationship
    {
        return $this->relationships[$name];
    }

    /**
     * @param  string  $name
     * @return boolean
     */
    public function hasRelationship(string $name) : bool
    {
        return isset($this->relationships[$name]) ?: false;
    }

    /**
     * Begin querying a mapper with eager loading.
     *
     * @param  array|string  $relations
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public function with($relations)
    {
        $relations = is_string($relations) ? func_get_args() : $relations;

        return $this->newQuery()->with($relations);
    }

    /**
     * Get all of the entities from the database.
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return $this->newQuery()->get();
    }

    /**
     * Eager load relations on the mapper.
     *
     * @param  array|string  $relations
     * @return $this
     */
    public function load($relations)
    {
        $query = $this->newQuery()->with(
            is_string($relations) ? func_get_args() : $relations
        );

        $query->eagerLoadRelations([$this]);

        return $this;
    }

    /**
     * Resolve a connection instance.
     *
     * @param  string|null  $connection
     * @return Connection
     */
    public static function resolveConnection(string $connection = null) : Connection
    {
        return static::$resolver->connection($connection);
    }

    /**
     * Get the connection resolver instance.
     *
     * @return Resolver
     */
    public static function getConnectionResolver() : Resolver
    {
        return static::$resolver;
    }

    /**
     * Set the connection resolver instance.
     *
     * @param  Resolver $resolver
     * @return void
     */
    public static function setConnectionResolver(Resolver $resolver) : void
    {
        static::$resolver = $resolver;
    }

    /**
     * Unset the connection resolver for entities.
     *
     * @return void
     */
    public static function unsetConnectionResolver() : void
    {
        static::$resolver = null;
    }

    /**
     * Set the event manager instance.
     *
     * @param  EventManagerInterface $eventManager
     * @return void
     */
    public static function setEventManager(EventManagerInterface $eventManager) : void
    {
        static::$eventManager = $eventManager;
    }

    /**
     * Unset the connection resolver for entities.
     *
     * @return void
     */
    public static function unsetEventManager() : void
    {
        static::$eventManager = null;
    }

    /**
     * @return void
     */
    public function flushEntityCache()
    {
        return $this->entityCache->flush();
    }

    /**
     * Get a new query builder for the mapper's table.
     *
     * @return Builder
     */
    public function newQuery() : Builder
    {
        $builder = $this->newQueryWithoutScopes();

        foreach ($this->getGlobalScopes() as $identifier => $scope) {
            $builder->withGlobalScope($identifier, $scope);
        }

        return $builder;
    }

    /**
     * Get a new query builder that doesn't have any global scopes.
     *
     * @return Builder|static
     */
    public function newQueryWithoutScopes()
    {
        $builder = $this->newHollowayBuilder($this->newBaseQueryBuilder());

        // Once we have the query builders, we will set the mapper instances so the
        // builder can easily access any information it may need from the mapper
        // while it is constructing and executing various queries against it.
        return $builder->setMapper($this)->with($this->with);
    }

    /**
     * Get a new query instance without a given scope.
     *
     * @param  \Illuminate\Database\Eloquent\Scope|string  $scope
     * @return Builder
     */
    public function newQueryWithoutScope($scope)
    {
        $builder = $this->newQuery();

        return $builder->withoutGlobalScope($scope);
    }

    /**
     * Create a new Eloquent query builder for the mapper.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return Builder|static
     */
    public function newHollowayBuilder($query)
    {
        return new Builder($query);
    }

    /**
     * Get a new query builder instance for the connection.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();

        return new QueryBuilder(
            $connection, $connection->getQueryGrammar(), $connection->getPostProcessor()
        );
    }

    /**
     * @param  stdClass $record
     * @return mixed
     */
    public function makeEntity(stdClass $record)
    {
        $primaryKey = $this->primaryKeyName;

        $this->entityCache->set($record->$primaryKey, (array) $record);

        $relations = collect($record->relations ?? []);

        unset($record->relations);

        return $this->hydrate($record, $relations);
    }

    /**
     * @param  Collection $records
     * @return Collection
     */
    public function makeEntities(Collection $records) : Collection
    {
        return $records->map(function($record) {
            return $this->makeEntity($record);
        });
    }

    /**
     * @param  array  $entities
     * @return Collection
     */
    public function newCollection(array $entities = []) : Collection
    {
        return new Collection($entities);
    }

    /**
     * @param  mixed $entity
     * @return bool
     */
    public function store($entity) : bool
    {
        if (is_iterable($entity)) {
            return $this->storeEntities($entity);
        } else {
            return $this->storeEntity($entity);
        }
    }

    /**
     * @param  mixed $entity
     * @return bool
     */
    public function remove($entity) : bool
    {
        if (is_iterable($entity)) {
            return $this->removeEntities($entity);
        } else {
            return $this->removeEntity($entity);
        }
    }

    /**
     * This method will by called by Holloway FactoryBuilder instances
     * when test factory's create() method is used to persist an entity.
     * You may override this at your convenience; By default, this method
     * simply proxies to the store() method.
     *
     * @param  mixed $entity
     * @return bool
     */
    public function factoryInsert($entity) : bool
    {
        return $this->store($entity);
    }

    /**
     * @param  iterable $entities
     * @return bool
     */
    protected function storeEntities(iterable $entities) : bool
    {
        $this->getConnection()->transaction(function() use ($entities) {
            foreach($entities as $entity) {
                $this->storeEntity($entity);
            }
        });

        return true;
    }

    /**
     * @param  mixed $entity
     * @return bool
     */
    protected function storeEntity($entity) : bool
    {
        if ($this->firePersistenceEvent('storing', $entity) === false) {
            return false;
        }

        $identifier = $this->getIdentifier($entity);
        $cached = null;

        if ($identifier) {
            $cached = $this->entityCache->get($identifier) ?: $this->find($identifier);
        }

        if ($cached) {
            if ($this->firePersistenceEvent('updating', $entity) !== false) {
                $attributes = $this->dehydrate($entity);

                // We compare the entity cache attributes to the current attributes on the entity.
                // If there are no dirty attributes then there should be no reason to update the entity in storage.
                if ($attributes !== $cached) {
                    $keyName = $this->getPrimaryKeyName();

                    if ($this->hasTimestamps === true) {
                        $now = new DateTime;
                        $attributes[static::UPDATED_AT] = $now;

                        if (method_exists($this, 'setUpdatedAtTimestampOnEntity')) {
                            $this->setUpdatedAtTimestampOnEntity($entity, $now);
                        }
                    }

                    $this->getConnection()
                        ->table($this->getTableName())
                        ->where($keyName, $identifier)
                        ->update($attributes);

                    $this->entityCache->set($this->getIdentifier($entity), $attributes);
                }

                $this->firePersistenceEvent('updated', $entity);
            } else {
                return false;
            }
        } else {
            if ($this->firePersistenceEvent('creating', $entity) !== false) {
                $attributes = $this->dehydrate($entity);

                if ($this->hasTimestamps === true) {
                    $now = new DateTime;
                    $attributes[static::CREATED_AT] = $now;
                    $attributes[static::UPDATED_AT] = $now;

                    if (method_exists($this, 'setCreatedAtTimestampOnEntity')) {
                        $this->setCreatedAtTimestampOnEntity($entity, $now);
                    }

                    if (method_exists($this, 'setUpdatedAtTimestampOnEntity')) {
                        $this->setUpdatedAtTimestampOnEntity($entity, $now);
                    }
                }

                $table = $this->getConnection()->table($this->getTableName());

                if ($this->isAutoIncrementing) {
                    $this->setIdentifier($entity, $table->insertGetId($attributes));
                } else {
                    $table->insert($attributes);
                }

                $this->entityCache->set($this->getIdentifier($entity), $attributes);

                $this->firePersistenceEvent('created', $entity);
            } else {
                return false;
            }
        }

        $this->firePersistenceEvent('stored', $entity);

        return true;
    }

    /**
     * @param  mixed $entity
     * @return bool
     */
    protected function removeEntity($entity) : bool
    {
        if ($this->firePersistenceEvent('removing', $entity) === false) {
            return false;
        }

        $identifier = $this->getIdentifier($entity);

        if (property_exists($this, 'isSoftDeleting') && $this->isSoftDeleting === true && $this->isForceDeleting === false) {
            $this->getConnection()
                ->table($this->getTableName())
                ->where($this->getPrimaryKeyName(), $identifier)
                ->update([$this->getQualifiedDeletedAtColumn() => new DateTime]);
        } else {
            $this->getConnection()
                ->table($this->getTableName())
                ->delete($identifier);
        }

        $this->entityCache->remove($identifier);

        $this->firePersistenceEvent('removed', $entity);

        return true;
    }

    /**
     * @param  iterable $entities
     * @return bool
     */
    protected function removeEntities(iterable $entities) : bool
    {
        $this->getConnection()->transaction(function() use ($entities) {
            foreach($entities as $entity) {
                $this->removeEntity($entity);
            }
        });

        return true;
    }


    // =======================================================================//
    // Global Scopes
    // =======================================================================//

    /**
     * Register a new global scope on the model.
     *
     * @param Scope|Closure|string  $scope
     * @param  Closure|null  $implementation
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public static function addGlobalScope($scope, Closure $implementation = null)
    {
        if (is_string($scope) && ! is_null($implementation)) {
            return static::$globalScopes[static::class][$scope] = $implementation;
        } elseif ($scope instanceof Closure) {
            return static::$globalScopes[static::class][spl_object_hash($scope)] = $scope;
        } elseif ($scope instanceof Scope) {
            return static::$globalScopes[static::class][get_class($scope)] = $scope;
        }

        throw new InvalidArgumentException('Global scope must be an instance of Closure or Scope.');
    }

    /**
     * Remove a registered global scope on the mapper.
     *
     * @param string $scope
     * @throws \InvalidArgumentException
     * @return void
     */
    public static function removeGlobalScope(string $scope)
    {
        if (static::hasGlobalScope($scope)) {
            if (is_string($scope)) {
                static::$globalScopes[static::class][$scope] = null;
            } elseif ($scope instanceof Scope) {
                static::$globalScopes[static::class][get_class($scope)] = null;
            } else {
                throw new InvalidArgumentException('Global scope must be a string name or an instance of Scope.');
            }
        }
    }

    /**
     * Determine if a model has a global scope.
     *
     * @param  Scope|string  $scope
     * @return bool
     */
    public static function hasGlobalScope($scope)
    {
        return ! is_null(static::getGlobalScope($scope));
    }

    /**
     * Get a global scope registered with the model.
     *
     * @param  Scope|string  $scope
     * @return Scope|\Closure|null
     */
    public static function getGlobalScope($scope)
    {
        if (is_string($scope)) {
            return \Illuminate\Support\Arr::get(static::$globalScopes, static::class.'.'.$scope);
        }

        return \Iluminate\Support\Arr::get(
            static::$globalScopes, static::class.'.'.get_class($scope)
        );
    }

    /**
     * Get the global scopes for this class instance.
     *
     * @return array
     */
    public function getGlobalScopes()
    {
        return \Illuminate\Support\Arr::get(static::$globalScopes, static::class, []);
    }

    /**
     * @param  string  $eventName
     * @param  mixed   $entity
     * @return void
     */
    protected function firePersistenceEvent(string $eventName, $entity)
    {
        return static::$eventManager->dispatch("$eventName: " . get_class($entity), $entity);
    }

    /**
     * @param  string   $eventName
     * @param  callable $callback
     * @return void
     */
    public function registerPersistenceEvent(string $eventName, callable $callback)
    {
        if (!$callback instanceof Closure) {
            $callback = Closure::fromCallable($callaback);
        }

        static::$eventManager->listen("$eventName: " . $this->entityClassName, $callback);
    }


    // =======================================================================//
    // Relationships
    // =======================================================================//

    /**
     * @param  string       $name
     * @param  string       $entityName
     * @param  string|null  $foreignKey
     * @param  string|null  $localKey
     * @return void
     */
    protected function hasOne(string $name, string $entityName, ?string $foreignKey = null, ?string $localKey = null)
    {
        $mapper = Holloway::instance()->getMapper($entityName);

        $foreignKey = $foreignKey ?? str_singular($this->getTableName()) . '_id';
        $localKey = $localKey ?? $this->primaryKeyName;

        $this->relationships[$name] = new Relationships\HasOne($name, $mapper->getTableName(), $foreignKey, $localKey, $entityName, $mapper->toBase());
    }

    /**
     * @param  string       $name
     * @param  string       $entityName
     * @param  string|null  $foreignKey
     * @param  string|null  $localKey
     * @return void
     */
    protected function hasMany(string $name, string $entityName, ?string $foreignKey = null, ?string $localKey = null)
    {
        $mapper = Holloway::instance()->getMapper($entityName);

        $foreignKey = $foreignKey ?? str_singular($this->getTableName()) . '_id';
        $localKey = $localKey ?? $this->primaryKeyName;

        $this->relationships[$name] = new Relationships\HasMany($name, $mapper->getTableName(), $foreignKey, $localKey, $entityName, $mapper->toBase());
    }

    /**
     * @param  string       $name
     * @param  string       $entityName
     * @param  string|null  $foreignKey
     * @param  string|null  $localKey
     * @return void
     */
    protected function belongsTo(string $name, string $entityName, ?string $foreignKey = null, ?string $localKey = null)
    {
        $mapper = Holloway::instance()->getMapper($entityName);

        $foreignKey = $foreignKey ?? str_singular($mapper->getTableName()) . '_id';
        $localKey = $localKey ?? $this->primaryKeyName;

        $this->relationships[$name] = new Relationships\BelongsTo($name, $mapper->getTableName(), $foreignKey, $localKey, $entityName, $mapper->toBase());
    }

    /**
     * @param  string       $name
     * @param  string       $entityName
     * @param  string|null  $pivotTableName
     * @param  string|null  $foreignKey
     * @param  string|null  $localKey
     * @return void
     */
    protected function belongsToMany(string $name, string $entityName, ?string $pivotTableName = null, ?string $pivotLocalKey = null, ?string $pivotForeignKey = null)
    {
        $mapper = Holloway::instance()->getMapper($entityName);

        $foreignKey = $mapper->getPrimaryKeyName();
        $localKey =  $this->getPrimaryKeyName();

        $localTableName = $this->getTableName();
        $foreignTableName = $mapper->getTableName();

        $pivotTableName = $pivotTableName ?? implode('_',  Arr::sort([$localTableName, $foreignTableName]));
        $pivotLocalKey = $pivotLocalKey ?? str_singular($localTableName) . '_id';
        $pivotForeignKey = $pivotForeignKey ?? str_singular($foreignTableName) . '_id';

        $this->relationships[$name] = new Relationships\BelongsToMany($name, $foreignTableName, $foreignKey, $localKey, $entityName, $pivotTableName, $pivotForeignKey, $pivotLocalKey, $mapper->toBase());
    }

    /**
     * @param  string    $name
     * @param  callable  $load
     * @param  callable  $for
     * @param  mixed     $entityClassName
     * @param  bool      $limitOne
     * @return void
     */
    public function custom(string $name, callable $load, callable $for, $mapOrEntityName, bool $limitOne = false)
    {
        /**
         * ALL OF THIS NEEDS REVISION: RIGHT NOW WE DON'T HAVE THE ABILITY TO LOAD NESTED RELATIONS FROM
         * A CUSTOM DEFINED RELATIONSHIP. THIS IS USED IN SCENARIOS WHERE THE ENTITY THAT'S BEING CREATED
         * FROM THE CUSTOM RELATIONSHIP DOES ACTUALLY HAVE A MAPPER DEFINED FOR IT BUT JUST NEEDS TO GET ITS
         * DATA LOADED FROM A CUSTOM QUERY, NOT A QUERY THAT'S GENERATED FROM THAT MAPPER. WE DIDN'T HAVE THIS
         * EDGE CASE WITH VENDOR INVOICE LINE ITEMS.
         *
         * FOR THIS SCENARIO, WE NEED TO MAKE THIS MORE FLEXIBLE SO THAT WE CAN SOMEHOW ALLOW DEVELOPERS TO USE THE EXISTING MAPPER FOR THE ENTITY
         * IN THE DEFINITION OF THE CUSTOM RELATIONSHIPS SO THAT THE DATA CAN BE LOADED IN A CUSTOM WAY, BUT THE MAPPER
         * WILL BE USED/INTROPSECTED LIKE A NORMAL MAPPER AND HAVE ITS DEFINED RELATIONSHIPS LOADED/PROCESSED INTO THE TREE (SOMEHOW?)
         */
        if (!$load instanceof Closure) {
            $load = Closure::fromCallable($load);
        }

        if (!$for instanceof Closure) {
            $for = Closure::fromCallable($for);
        }

        if (is_callable($mapOrEntityName) && !$mapOrEntityName instanceof Closure) {
            $mapOrEntityName = $mapOrEntityName = Closure::fromCallable($mapOrEntityName);
        }

        $this->relationships[$name] = new Relationships\Custom($name, $load, $for, $mapOrEntityName, $limitOne, $this->newQuery()->toBase());
    }

    /**
     * Return a collection of related records (Alias for the custom() method).
     *
     * @param  string           $name
     * @param  callable         $load
     * @param  callable         $for
     * @param  string|callable  $mapOrEntityName
     * @return void
     */
    public function customMany(string $name, callable $load, callable $for, $mapOrEntityName)
    {
        return $this->custom($name, $load, $for, $mapOrEntityName);
    }

    /**
     * Return a single related record.
     *
     * @param  string           $name
     * @param  callable         $load
     * @param  callable         $for
     * @param  string|callable  $mapOrEntityName
     * @return void
     */
    public function customOne(string $name, callable $load, callable $for, $mapOrEntityName)
    {
        return $this->custom($name, $load, $for, $mapOrEntityName, true);
    }


    // =======================================================================//
    // GETTERS / SETTERS
    // =======================================================================//


    /**
     * @return Connection
     */
    public function getConnection() : Connection
    {
        return static::resolveConnection($this->getConnectionName());
    }

    /**
     * @return string
     */
    public function getConnectionName() : string
    {
        return $this->connection;
    }

    /**
     * @param  string  $name
     * @return void
     */
    public function setConnection(string $name)
    {
        $this->connection = $name;
    }

    /**
     * Get the auto incrementing key type.
     *
     * @return string
     */
    public function getKeyType()
    {
        return $this->keyType;
    }

    /**
     * Get the value indicating whether the IDs are incrementing.
     *
     * @return bool
     */
    public function getIncrementing()
    {
        return $this->incrementing;
    }

    /**
     * Set whether IDs are incrementing.
     *
     * @param  bool  $value
     * @return $this
     */
    public function setIncrementing($value)
    {
        $this->incrementing = $value;

        return $this;
    }

    /**
     * Get the queueable identity for the entity.
     *
     * @return mixed
     */
    public function getQueueableId()
    {
        return $this->primaryKeyName;
    }

    /**
     * Get the default foreign key name for the mapper.
     *
     * @return string
     */
    public function getForeignKeyName() : string
    {
        $entityClassName = $this->getEntityClassName();

        return Str::snake(class_basename($entityClassName)) . '_' . $this->primaryKeyName;
    }

    /**
     * Get the number of entities to return per page.
     *
     * @return int
     */
    public function getPerPage() : int
    {
        return $this->perPage;
    }

    /**
     * Set the number of entities to return per page.
     *
     * @param  int  $perPage
     * @return self
     */
    public function setPerPage(int $perPage) : self
    {
        $this->perPage = $perPage;

        return $this;
    }

    /**
     * @return array
     */
    public function getRelationships() : array
    {
        return $this->relationships;
    }

    /**
     * @return array
     */
    public function getWith() : array
    {
        return $this->with;
    }

    /**
     * @return string
     */
    public function getTableName() : string
    {
        if (!$this->tableName) {
            $entityClassName = $this->getEntityClassName();

            return str_replace('\\', '', Str::snake(Str::plural(class_basename($entityClassName))));
        }

        return $this->tableName;
    }

    /**
     * @param  string  $tableName
     * @return void
     */
    public function setTableName(string $tableName)
    {
        $this->tableName = $tableName;
    }

    /**
     * @return string
     */
    public function getPrimaryKeyName() : string
    {
        return $this->primaryKeyName;
    }

    /**
     * @param  string  $primaryKeyName
     * @return void
     */
    public function setPrimaryKeyName(string $primaryKeyName)
    {
        $this->primaryKeyName = $primaryKeyName;
    }

    /**
     * @return string
     */
    public function getQualifiedKeyName() : string
    {
        return $this->getTableName() . '.' . $this->primaryKeyName;
    }
}