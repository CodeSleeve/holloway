<?php

namespace Holloway;

use Carbon\Carbon;
use Holloway\Entities\Entity;
use Holloway\Functions\Arr;
use Illuminate\Contracts\Events\Dispatcher as EventManagerInterface;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\{Connection, ConnectionResolverInterface as Resolver};
use Illuminate\Support\{Collection, Str};
use InvalidArgumentException;
use Throwable;
use stdClass;
use Closure;

abstract class Mapper
{
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
    abstract public function setIdentifier($entity, $value) : void;

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
     * @param  string   $eventName
     * @param  callable $callback
     * @return void
     */
    public static function registerEntityEvent(string $eventName, callable $callback)
    {

    }

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

        $this->entityCache->add($record->$primaryKey, (array) $record);

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
     * @param string $method
     * @param array  $parameters
     */
    public function __call(string $method , array $parameters)
    {
        if (in_array($method, ['increment', 'decrement'])) {
            return $this->$method(...$parameters);
        }

        return $this->newQuery()->$method(...$parameters);
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
        if ($this->firePersistanceEvent('storing', $entity) === false) {
            return false;
        }

        if ($this->entityCache->has($this->getIdentifier($entity))) {
            if ($this->firePersistanceEvent('updating', $entity) !== false) {
                $keyName = $this->getPrimaryKeyName();
                $key = $this->getIdentifier($entity);

                $this->getConnection()
                    ->table($this->getTableName())
                    ->where($keyName, $key)
                    ->update($this->dehydrate($entity));

                $this->firePersistanceEvent('updated', $entity);
            } else {
                return false;
            }
        } else {
            if ($this->firePersistanceEvent('creating', $entity) !== false) {
                $this->getConnection()
                    ->table($this->getTableName())
                    ->insert($this->dehydrate($entity));

                $this->firePersistanceEvent('created', $entity);
            } else {
                return false;
            }
        }

        $this->firePersistanceEvent('stored', $entity);

        return true;
    }

    /**
     * @param  mixed $entity
     * @return bool
     */
    protected function removeEntity($entity) : bool
    {
        if ($this->firePersistanceEvent('removing', $entity) === false) {
            return false;
        }

        if (property_exists($this, 'isSoftDeleting') && $this->isSoftDeleting === true) {
            $this->getConnection()
                ->table($this->getTableName())
                ->where($this->getPrimaryKeyName(), $this->getIdentifier($entity))
                ->update([$this->getQualifiedDeletedAtColumn() => Carbon::now()]);
        } else {
            $this->getConnection()
                ->table($this->getTableName())
                ->delete($this->getIdentifier($entity));
        }


        $this->firePersistanceEvent('removed', $entity);

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
    protected function firePersistanceEvent(string $eventName, $entity)
    {
        return static::$eventManager->fire("$eventName: " . get_class($entity), $entity);
    }


    // =======================================================================//
    // Relationships
    // =======================================================================//

    /**
     * @param  string       $name
     * @param  string       $entityName
     * @param  string|null  $foreignKey
     * @param  string|null  $localKey
     * @return Relationships\HasOne
     */
    protected function hasOne(string $name, string $entityName, ?string $foreignKey = null, ?string $localKey = null)
    {
        $mapper = Holloway::instance()->getMapper($entityName);

        $foreignKey = $foreignKey ?? str_singular($this->getTableName()) . '_id';
        $localKey = $localKey ?? $this->primaryKeyName;

        $this->relationships[$name] = new Relationships\HasOne($name, $mapper->getTableName(), $foreignKey, $localKey, $entityName, $mapper->getConnection());
    }

    /**
     * @param  string       $name
     * @param  string       $entityName
     * @param  string|null  $foreignKey
     * @param  string|null  $localKey
     * @return Relationships\HasMany
     */
    protected function hasMany(string $name, string $entityName, ?string $foreignKey = null, ?string $localKey = null)
    {
        $mapper = Holloway::instance()->getMapper($entityName);

        $foreignKey = $foreignKey ?? str_singular($this->getTableName()) . '_id';
        $localKey = $localKey ?? $this->primaryKeyName;

        $this->relationships[$name] = new Relationships\HasMany($name, $mapper->getTableName(), $foreignKey, $localKey, $entityName, $mapper->getConnection());
    }

    /**
     * @param  string       $name
     * @param  string       $entityName
     * @param  string|null  $foreignKey
     * @param  string|null  $localKey
     * @return Relationships\BelongsTo
     */
    protected function belongsTo(string $name, string $entityName, ?string $foreignKey = null, ?string $localKey = null)
    {
        $mapper = Holloway::instance()->getMapper($entityName);

        $foreignKey = $foreignKey ?? str_singular($mapper->getTableName()) . '_id';
        $localKey = $localKey ?? $this->primaryKeyName;

        $this->relationships[$name] = new Relationships\BelongsTo($name, $mapper->getTableName(), $foreignKey, $localKey, $entityName, $mapper->getConnection());
    }

    /**
     * @param  string       $name
     * @param  string       $entityName
     * @param  string|null  $pivotTableName
     * @param  string|null  $foreignKey
     * @param  string|null  $localKey
     * @return Relationships\BelongsToMany
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

        $this->relationships[$name] = new Relationships\BelongsToMany($name, $foreignTableName, $foreignKey, $localKey, $entityName, $pivotTableName, $pivotForeignKey, $pivotLocalKey, $mapper->getConnection());
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