<?php

namespace CodeSleeve\Holloway;

use Closure;
use DateTime;
use stdClass;
use InvalidArgumentException;
use CodeSleeve\Holloway\Functions\Arr;
use Doctrine\Instantiator\Instantiator;
use Illuminate\Support\{Collection, Str};
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Contracts\Events\Dispatcher as EventManagerInterface;
use Illuminate\Database\{Connection, ConnectionResolverInterface as Resolver};

abstract class Mapper
{
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    const DEFAULT_TIME_ZONE = 'UTC';
    
    protected static ?Resolver $resolver = null;
    protected static ?EventManagerInterface $eventManager = null;
    protected Instantiator $instantiator;
    protected static Dispatcher $dispatcher;
    protected EntityCache $entityCache;
    protected string $entityClassName = '';
    protected string $connection = '';
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected string $keyType = 'int';
    protected int $perPage = 15;
    protected bool $hasTimestamps = true;
    protected string $timestampFormat = 'Y-m-d H:i:s';
    protected bool $isAutoIncrementing = true;
    protected array $relationships = [];
    protected array $with = [];
    protected static array $globalScopes = [];

    /**
     * Create a new mapper intance.
     */
    public function __construct()
    {
        $this->entityCache = new EntityCache($this->primaryKey);
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
    abstract public function setIdentifier($entity, $value) : void;

    /**
     * @param  stdClass   $record
     * @param  Collection $relations
     * @return mixed
     */
    abstract public function hydrate(stdClass $record, Collection $relations);

    /**
     * @param  mixed $entity
     * @return array
     */
    abstract public function dehydrate($entity) : array;

    /**
     * Instantiate a new entity instance.
     */
    public function instantiateEntity(array $attributes)
    {
        return $this->instantiator->instantiate($this->entityClassName);
    }

    /**
     * Get the name of the created at column used by this mapper.
     */
    public function getCreatedAtColumnName() : string
    {
        return 'created_at';
    }

    /**
     * Get the name of the updated at column used by this mapper.
     */
    public function getUpdatedAtColumnName() : string
    {
        return 'updated_at';
    }

    /**
     * Clear the entity cache on this mapper.
     */
    public function clearEntityCache() : void
    {
        $this->entityCache->flush();
    }

    /**
     * Get the number of cached entities on this mapper.
     */
    public function getNumberOfCachedEntities() : int
    {
        return $this->entityCache->count();
    }

    /**
    * Retrieve the given relationship from this mapper or throw an exception if it hasn't been defined.
    */
    public function getRelationship(string $name) : Relationships\Relationship
    {
        if (isset($this->relationships[$name])) {
            return $this->relationships[$name];
        } else {
            throw new Exceptions\UknownRelationshipException("The $name relationship hasn't been defined on the " . get_class($this) . " mapper.", 1);
        }
    }

    /**
    * Determine whether the given relationship name is defined on this mapper.
    */
    public function hasRelationship(string $name) : bool
    {
        return isset($this->relationships[$name]) ?: false;
    }

    /**
     * Begin querying a mapper with eager loading.
     */
    public function with($relations) : Builder
    {
        $relations = is_string($relations) ? func_get_args() : $relations;

        return $this->newQuery()->with($relations);
    }

    /**
     * Get all of the entities from the database.
     */
    public function all() : Collection
    {
        return $this->newQuery()->get();
    }

    /**
     * Eager load relations on the mapper.
     *
     * @param  mixed  $relations
     */
    public function load($relations) : self
    {
        $query = $this->newQuery()->with(
            is_string($relations) ? func_get_args() : $relations
        );

        $query->eagerLoadRelations([$this]);

        return $this;
    }

    /**
     * Resolve a connection instance.
     */
    public static function resolveConnection(?string $connection = null) : Connection
    {
        return static::$resolver->connection($connection);
    }

    /**
     * Get the connection resolver instance.
     */
    public static function getConnectionResolver() : Resolver
    {
        return static::$resolver;
    }

    /**
     * Set the connection resolver instance.
     */
    public static function setConnectionResolver(Resolver $resolver) : void
    {
        static::$resolver = $resolver;
    }

    /**
     * Unset the connection resolver for entities.
     */
    public static function unsetConnectionResolver() : void
    {
        static::$resolver = null;
    }

    /**
     * Set the event manager instance.
     */
    public static function setEventManager(EventManagerInterface $eventManager) : void
    {
        static::$eventManager = $eventManager;
    }

    /**
     * Unset the event manager for entities.
     */
    public static function unsetEventManager() : void
    {
        static::$eventManager = null;
    }

    /**
     * Flush this mapper's entity cache.
     */
    public function flushEntityCache() : void
    {
        $this->entityCache->flush();
    }

    /**
     * Get a new query builder for the mapper's table.
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
     */
    public function newQueryWithoutScope(Scope|string $scope) : Builder
    {
        $builder = $this->newQuery();

        return $builder->withoutGlobalScope($scope);
    }

    /**
     * Create a new Holloway query builder for the mapper.
     */
    public function newHollowayBuilder(QueryBuilder $query) : Builder
    {
        return new Builder($query);
    }

    /**
     * Get a new query builder instance for the connection.
     */
    protected function newBaseQueryBuilder() : QueryBuilder
    {
        $connection = $this->getConnection();

        return new QueryBuilder(
            $connection, 
            $connection->getQueryGrammar(), 
            $connection->getPostProcessor()
        );
    }

    /**
     * Make a new entity instance using a stdClass record from storage.
     */
    public function makeEntity(stdClass $record) : mixed
    {
        $primaryKey = $this->primaryKey;

        $this->entityCache->set($record->$primaryKey, (array) $record);

        $relations = new Collection($record->relations ?? []);

        unset($record->relations);

        return $this->hydrate($record, $relations);
    }

    /**
     * Make a collection of new entity instances from a Collection of stdClass records.
     */
    public function makeEntities(Collection $records) : Collection
    {
        return $records->map(function($record) {
            return $this->makeEntity($record);
        });
    }

    /**
     * Return a new collection of Entities.
     */
    public function newCollection(array $entities = []) : Collection
    {
        return new Collection($entities);
    }

    /**
     * Persist a single entity or collection of entities to storage.
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
     * Remove a single entity or collection of entities from storage.
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
     */
    public function factoryInsert($entity) : bool
    {
        return $this->store($entity);
    }

    /**
     * Persist a collection of entities into storage.
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
     * Persist a single entity into storage.
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
                    $keyName = $this->getKeyName();

                    if ($this->hasTimestamps === true) {
                        $attributes = \Illuminate\Support\Arr::except($attributes, [self::UPDATED_AT, self::CREATED_AT]);
                        $now = new DateTime('now', new \DateTimeZone(static::DEFAULT_TIME_ZONE));
                        $attributes[static::UPDATED_AT] = $now->format($this->timestampFormat);

                        if (method_exists($this, 'setUpdatedAtTimestampOnEntity')) {
                            $this->setUpdatedAtTimestampOnEntity($entity, $now);
                        }
                    }

                    $this->getConnection()
                        ->table($this->getTable())
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
                    $now = new DateTime('now', new \DateTimeZone(static::DEFAULT_TIME_ZONE));
                    $attributes[static::CREATED_AT] = $now->format($this->timestampFormat);
                    $attributes[static::UPDATED_AT] = $now->format($this->timestampFormat);

                    if (method_exists($this, 'setCreatedAtTimestampOnEntity')) {
                        $this->setCreatedAtTimestampOnEntity($entity, $now);
                    }

                    if (method_exists($this, 'setUpdatedAtTimestampOnEntity')) {
                        $this->setUpdatedAtTimestampOnEntity($entity, $now);
                    }
                }

                $table = $this->getConnection()->table($this->getTable());

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
     * Remove a single entity from storage.
     */
    protected function removeEntity($entity) : bool
    {
        if ($this->firePersistenceEvent('removing', $entity) === false) {
            return false;
        }

        $identifier = $this->getIdentifier($entity);

        if (property_exists($this, 'isSoftDeleting') && $this->isSoftDeleting === true && $this->isForceDeleting === false) {
            $this->getConnection()
                ->table($this->getTable())
                ->where($this->getKeyName(), $identifier)
                ->update([$this->getQualifiedDeletedAtColumn() => new DateTime]);
        } else {
            $this->getConnection()
                ->table($this->getTable())
                ->delete($identifier);
        }

        $this->entityCache->remove($identifier);

        $this->firePersistenceEvent('removed', $entity);

        return true;
    }

    /**
     * Remove a collection of entities from storage.
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
     * Register a new global scope on this mapper.
     *
     * @throws InvalidArgumentException
     */
    public static function addGlobalScope(Scope|Closure|string $scope, ?Closure $implementation = null) : void
    {
        if (is_string($scope) && ! is_null($implementation)) {
            static::$globalScopes[static::class][$scope] = $implementation;

            return;
        } elseif ($scope instanceof Closure) {
            static::$globalScopes[static::class][spl_object_hash($scope)] = $scope;

            return;
        } elseif ($scope instanceof Scope) {
            static::$globalScopes[static::class][get_class($scope)] = $scope;

            return;
        }

        throw new InvalidArgumentException('Global scope must be an instance of string, Closure, or Scope.');
    }

    /**
     * Remove a registered global scope on the mapper.
     */
    public static function removeGlobalScope(Scope|string $scope) : void
    {
        if (static::hasGlobalScope($scope)) {
            if (is_string($scope)) {
                unset(static::$globalScopes[static::class][$scope]);
            } elseif ($scope instanceof Scope) {
                unset(static::$globalScopes[static::class][get_class($scope)]);
            } else {
                throw new InvalidArgumentException('Global scope must be a string name or an instance of Scope.');
            }
        }
    }

    /**
     * Determine if a model has a global scope.
     */
    public static function hasGlobalScope(Scope|string $scope) : bool
    {
        return ! is_null(static::getGlobalScope($scope));
    }

    /**
     * Get a global scope registered with the model.
     */
    public static function getGlobalScope(Scope|string $scope) : Scope|Closure|string|null
    {
        if (is_string($scope)) {
            return \Illuminate\Support\Arr::get(static::$globalScopes, static::class.'.'.$scope);
        }

        return \Illuminate\Support\Arr::get(
            static::$globalScopes, static::class.'.'.get_class($scope)
        );
    }

    /**
     * Get the global scopes for this class instance.
     */
    public function getGlobalScopes() : array
    {
        return \Illuminate\Support\Arr::get(static::$globalScopes, static::class, []);
    }

    /**
    * Fire a persistence event for the given entity.
    */
    protected function firePersistenceEvent(string $eventName, $entity)
    {
        return static::$eventManager->dispatch("$eventName: " . get_class($entity), $entity);
    }

    /**
     * Register a new persistence event listern.
     */
    public function registerPersistenceEvent(string $eventName, callable $callback) : void
    {
        if (!$callback instanceof Closure) {
            $callback = Closure::fromCallable($callback);
        }

        static::$eventManager->listen("$eventName: " . $this->entityClassName, $callback);
    }


    // =======================================================================//
    // Relationships
    // =======================================================================//

    /**
     * Define a new hasOne relationship
     */
    protected function hasOne(string $name, string $entityName, ?string $foreignKey = null, ?string $localKey = null) : void
    {
        $mapper = Holloway::instance()->getMapper($entityName);

        $foreignKey = $foreignKey ?? Str::singular($this->getTable()) . '_id';
        $localKey = $localKey ?? $this->primaryKey;

        $this->relationships[$name] = new Relationships\HasOne($name, $mapper->getTable(), $foreignKey, $localKey, $entityName, fn() => $mapper->toBase());
    }

    /**
     * Define a new hasMany relationship
     */
    protected function hasMany(string $name, string $entityName, ?string $foreignKey = null, ?string $localKey = null)
    {
        $mapper = Holloway::instance()->getMapper($entityName);

        $foreignKey = $foreignKey ?? Str::singular($this->getTable()) . '_id';
        $localKey = $localKey ?? $this->primaryKey;

        $this->relationships[$name] = new Relationships\HasMany($name, $mapper->getTable(), $foreignKey, $localKey, $entityName, fn() => $mapper->toBase());
    }

    /**
     * Define a new belongsTo relationship
     */
    protected function belongsTo(string $name, string $entityName, ?string $foreignKey = null, ?string $localKey = null) : void
    {
        $mapper = Holloway::instance()->getMapper($entityName);

        $foreignKey = $foreignKey ?? Str::singular($mapper->getTable()) . '_id';
        $localKey = $localKey ?? $this->primaryKey;

        $this->relationships[$name] = new Relationships\BelongsTo($name, $mapper->getTable(), $foreignKey, $localKey, $entityName, fn() => $mapper->toBase());
    }

    /**
     * Define a new belongsToMany relationship
     */
    protected function belongsToMany(string $name, string $entityName, ?string $pivotTableName = null, ?string $pivotLocalKey = null, ?string $pivotForeignKey = null) : void
    {
        $mapper = Holloway::instance()->getMapper($entityName);

        $foreignKey = $mapper->getKeyName();
        $localKey =  $this->getKeyName();

        $localTableName = $this->getTable();
        $foreignTableName = $mapper->getTable();

        $pivotTableName = $pivotTableName ?? implode('_',  Arr::sort([$localTableName, $foreignTableName]));
        $pivotLocalKey = $pivotLocalKey ?? Str::singular($localTableName) . '_id';
        $pivotForeignKey = $pivotForeignKey ?? Str::singular($foreignTableName) . '_id';

        $this->relationships[$name] = new Relationships\BelongsToMany($name, $foreignTableName, $foreignKey, $localKey, $entityName, $pivotTableName, $pivotForeignKey, $pivotLocalKey, fn() => $mapper->toBase());
    }

    /**
     * Define a new custom relationship
     */
    public function custom(string $name, callable $load, callable $for, $mapOrEntityName, bool $limitOne = false) : void
    {
        if (!$load instanceof Closure) {
            $load = Closure::fromCallable($load);
        }

        if (!$for instanceof Closure) {
            $for = Closure::fromCallable($for);
        }

        if (is_callable($mapOrEntityName) && !$mapOrEntityName instanceof Closure) {
            $mapOrEntityName = $mapOrEntityName = Closure::fromCallable($mapOrEntityName);
        }

        $this->relationships[$name] = new Relationships\Custom($name, $load, $for, $mapOrEntityName, $limitOne, fn() => $this->newQueryWithoutScopes()->toBase());
    }

    /**
     * Define a new customMany relationship
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


    public function getConnection() : Connection
    {
        return static::resolveConnection($this->getConnectionName());
    }

    public function getConnectionName() : string
    {
        return $this->connection;
    }

    public function setConnection(string $name) : void
    {
        $this->connection = $name;
    }

    /**
     * Get the auto incrementing key type.
     */
    public function getKeyType() : string
    {
        return $this->keyType;
    }

    /**
     * Get the value indicating whether the IDs are incrementing.
     */
    public function getIsAutoIncrementing() : bool
    {
        return $this->isAutoIncrementing;
    }

    /**
     * Set whether IDs are incrementing.
     */
    public function setIsAutoIncrementing(bool $value) : void
    {
        $this->isAutoIncrementing = $value;
    }

    /**
     * Get the queueable identity for the entity.
     */
    public function getQueueableId() : string
    {
        return $this->primaryKey;
    }

    /**
     * Get the default foreign key name for the mapper.
     */
    public function getForeignKeyName() : string
    {
        $entityClassName = $this->getEntityClassName();

        return Str::snake(class_basename($entityClassName)) . '_' . $this->primaryKey;
    }

    /**
     * Get the number of entities to return per page.
     */
    public function getPerPage() : int
    {
        return $this->perPage;
    }

    /**
     * Set the number of entities to return per page.
     */
    public function setPerPage(int $perPage) : self
    {
        $this->perPage = $perPage;

        return $this;
    }

    /**
     * Get the relationships defined on this mapper.
     */
    public function getRelationships() : array
    {
        return $this->relationships;
    }

    /**
     * Get the eager loads defined on this mapper.
     */
    public function getWith() : array
    {
        return $this->with;
    }

    /**
     * Get the name of the table that's used by this mapper.
     */
    public function getTable() : string
    {
        if (!$this->table) {
            $entityClassName = $this->getEntityClassName();

            return str_replace('\\', '', Str::snake(Str::plural(class_basename($entityClassName))));
        }

        return $this->table;
    }

    /**
     * Set the name of the table that used by this mapper.
     */
    public function setTable(string $table) : void
    {
        $this->table = $table;
    }

    /**
     * Get the name of the primary key column on the table used by this mapper.
     */
    public function getKeyName() : string
    {
        return $this->primaryKey;
    }

    /**
     * Set the name of the primary key column of the table that's used by this mapper.
     */
    public function setKeyName(string $primaryKey) : void
    {
        $this->primaryKey = $primaryKey;
    }

    /**
     * Get the fully qualified primary key column name for the table that's used by this mapper.
     */
    public function getQualifiedKeyName() : string
    {
        return $this->getTable() . '.' . $this->primaryKey;
    }
}