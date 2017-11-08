<?php

namespace Holloway;

use BadMethodCallException;
use Closure;
use Holloway\Relationships\Tree;
use Illuminate\Contracts\Pagination\{Paginator as PaginatorContract, LengthAwarePaginator as LengthAwarePaginatorContract};
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\{Paginator, LengthAwarePaginator};
use Illuminate\Support\{Collection, Str};

class Builder
{
    /**
     * The base query builder instance.
     *
     * @var QueryBuilder
     */
    protected $query;

    /**
     * The mapper for this builder.
     *
     * @var Mapper
     */
    protected $mapper;

    /**
     * The relationship tree that should be loaded with this query.
     *
     * @var Tree
     */
    protected $tree;

    /**
     * All of the globally registered builder macros.
     *
     * @var array
     */
    protected static $macros = [];

    /**
     * All of the locally registered builder macros.
     *
     * @var array
     */
    protected $localMacros = [];

    /**
     * A replacement for the typical delete function.
     *
     * @var \Closure
     */
    protected $onDelete;

    /**
     * The methods that should be returned from query builder.
     *
     * @var array
     */
    protected $passthru = [
        'insert', 'insertGetId', 'getBindings', 'toSql',
        'exists', 'count', 'min', 'max', 'avg', 'sum', 'getConnection',
    ];

    /**
     * Applied global scopes.
     *
     * @var array
     */
    protected $scopes = [];

    /**
     * Removed global scopes.
     *
     * @var array
     */
    protected $removedScopes = [];

    /**
     * @param  \Illuminate\Database\Query\Builder $query
     * @return void
     */
    public function __construct(QueryBuilder $query)
    {
        $this->query = $query;
    }

     /**
     * Dynamically handle calls into the query instance.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if ($method === 'macro') {
            $this->localMacros[$parameters[0]] = $parameters[1];

            return;
        }

        if (isset($this->localMacros[$method])) {
            array_unshift($parameters, $this);

            return $this->localMacros[$method](...$parameters);
        }

        if (isset(static::$macros[$method])) {
            return call_user_func_array(static::$macros[$method]->bindTo($this, static::class), $parameters);
        }

        if (method_exists($this->mapper, $scope = 'scope'.ucfirst($method))) {
            return $this->callScope([$this->mapper, $scope], $parameters);
        }

        if (in_array($method, $this->passthru)) {
            return $this->toBase()->{$method}(...$parameters);
        }

        $this->query->{$method}(...$parameters);

        return $this;
    }

    /**
     * Dynamically handle calls into the query instance.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @throws \BadMethodCallException
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        if ($method === 'macro') {
            static::$macros[$parameters[0]] = $parameters[1];

            return;
        }

        if (! isset(static::$macros[$method])) {
            throw new BadMethodCallException("Method {$method} does not exist.");
        }

        if (static::$macros[$method] instanceof Closure) {
            return call_user_func_array(Closure::bind(static::$macros[$method], null, static::class), $parameters);
        }

        return call_user_func_array(static::$macros[$method], $parameters);
    }

    /**
     * @return Mapper
     */
    public function getMapper() : Mapper
    {
        return $this->mapper;
    }

    /**
     *  @param  Mapper $mapper
     *  @return Builder
     */
    public function setMapper(Mapper $mapper) : Builder
    {
        $this->mapper = $mapper;

        $this->query->from($mapper->getTableName());

        return $this;
    }

    /**
     * @param  mixed  $id
     * @return mixed
     */
    public function find($id)
    {
        if (is_array($id)) {
            return $this->findMany($id);
        }

        return $this->whereKey($id)->first();
    }

    /**
     * @param  mixed $id
     * @throws ModelNotFoundException
     * @return mixed
     */
    public function findOrFail($id)
    {
        $entity = $this->find($id);

        if (!$entity) {
            throw (new ModelNotFoundException)->setModel($this->mapper->getAbstractEntityClassName(), $id);
        }

        return $entity;
    }

    /**
     * @param  array  $ids
     * @return \Illuminate\Support\Collection
     */
    public function findMany($ids) : Collection
    {
        if (empty($ids)) {
            return collect();
        }

        return $this->whereKey($ids)->get($columns);
    }

    /**
     * Execute the query and get the first result.
     *
     * @return mixed|null
     */
    public function first()
    {
       return $this->take(1)->get()->first();
    }

    /**
     * Add a where clause on the primary key to the query.
     *
     * @param  mixed  $id
     * @return Builder
     */
    public function whereKey($id) : Builder
    {
        if (is_array($id) || $id instanceof Arrayable) {
            $this->query->whereIn($this->mapper->getQualifiedKeyName(), $id);

            return $this;
        }

        return $this->where($this->mapper->getQualifiedKeyName(), '=', $id);
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param  string|\Closure  $column
     * @param  string  $operator
     * @param  mixed   $value
     * @param  string  $boolean
     * @return Builder
     */
    public function where($column, string $operator = null, $value = null, string $boolean = 'and') : Builder
    {
        if ($column instanceof Closure) {
            $query = $this->mapper->newQueryWithoutScopes();

            $column($query);

            $this->query->addNestedWhereQuery($query->getQuery(), $boolean);
        } else {
            $this->query->where(...func_get_args());
        }

        return $this;
    }

    /**
     * Add an "or where" clause to the query.
     *
     * @param  string|\Closure  $column
     * @param  string           $operator
     * @param  mixed            $value
     * @return Builder
     */
    public function orWhere($column, string $operator = null, $value = null) : Builder
    {
        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array  $columns
     * @return Collection
     */
    public function get() : Collection
    {
        $records = $this->applyScopes()->query->get();
        $records = $this->getTree()->loadInto($records);

        return $this->mapper->makeEntities($records);
    }

    /**
     * Call the given local mapper scopes.
     *
     * @param  array  $scopes
     * @return mixed
     */
    public function scopes(array $scopes)
    {
        $builder = $this;

        foreach ($scopes as $scope => $parameters) {
            // If the scope key is an integer, then the scope was passed as the value and
            // the parameter list is empty, so we will format the scope name and these
            // parameters here. Then, we'll be ready to call the scope on the mapper.
            if (is_int($scope)) {
                list($scope, $parameters) = [$parameters, []];
            }

            // Next we'll pass the scope callback to the callScope method which will take
            // care of groping the "wheres" correctly so the logical order doesn't get
            // messed up when adding scopes. Then we'll return back out the builder.
            $builder = $builder->callScope([$this->mapper, 'scope'.ucfirst($scope)], (array) $parameters);
        }

        return $builder;
    }

    /**
     * Apply any global scopes to the Holloway builder instance and return it.
     *
     * @return Builder
     */
    public function applyScopes() : Builder
    {
        if (! $this->scopes) {
            return $this;
        }

        $builder = clone $this;

        foreach ($this->scopes as $scope) {
            $builder->callScope(function (Builder $builder) use ($scope) {
                // If the scope is a Closure we will just go ahead and call the scope with the
                // builder instance. The "callScope" method will properly group the clauses
                // that are added to this query so "where" clauses maintain proper logic.
                if ($scope instanceof Closure) {
                    $scope($builder);
                }

                // If the scope is a scope object, we will call the apply method on this scope
                // passing in the builder and the model instance. After we run all of these
                // scopes we will return back the builder instance to the outside caller.
                if ($scope instanceof Scope) {
                    $scope->apply($builder, $this->getMapper());
                }
            });
        }

        return $builder;
    }

    /**
     * Return the parsed eager loads for this query.
     *
     * @return array
     */
    public function getLoads() : array
    {
        return $this->loads;
    }

    /**
     * Apply the given scope on the current builder instance.
     *
     * @param  callable $scope
     * @param  array $parameters
     * @return mixed
     */
    protected function callScope(callable $scope, $parameters = [])
    {
        array_unshift($parameters, $this);

        $query = $this->getQuery();

        // We will keep track of how many wheres are on the query before running the
        // scope so that we can properly group the added scope constraints in the
        // query as their own isolated nested where statement and avoid issues.
        $originalWhereCount = count($query->wheres);

        $result = $scope(...array_values($parameters)) ?: $this;

        if (count($query->wheres) > $originalWhereCount) {
            $this->addNewWheresWithinGroup($query, $originalWhereCount);
        }

        return $result;
    }

    /**
     * Nest where conditions by slicing them at the given where count.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  int  $originalWhereCount
     * @return void
     */
    protected function addNewWheresWithinGroup(QueryBuilder $query, $originalWhereCount)
    {
        // Here, we totally remove all of the where clauses since we are going to
        // rebuild them as nested queries by slicing the groups of wheres into
        // their own sections. This is to prevent any confusing logic order.
        $allWheres = $query->wheres;

        $query->wheres = [];

        $this->groupWhereSliceForScope(
            $query, array_slice($allWheres, 0, $originalWhereCount)
        );

        $this->groupWhereSliceForScope(
            $query, array_slice($allWheres, $originalWhereCount)
        );
    }

    /**
     * Slice where conditions at the given offset and add them to the query as a nested condition.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $whereSlice
     * @return void
     */
    protected function groupWhereSliceForScope(QueryBuilder $query, $whereSlice)
    {
        $whereBooleans = collect($whereSlice)->pluck('boolean');

        // Here we'll check if the given subset of where clauses contains any "or"
        // booleans and in this case create a nested where expression. That way
        // we don't add any unnecessary nesting thus keeping the query clean.
        if ($whereBooleans->contains('or')) {
            $query->wheres[] = $this->createNestedWhere(
                $whereSlice, $whereBooleans->first()
            );
        } else {
            $query->wheres = array_merge($query->wheres, $whereSlice);
        }
    }

    /**
     * Create a where array with nested where conditions.
     *
     * @param  array  $whereSlice
     * @param  string  $boolean
     * @return array
     */
    protected function createNestedWhere($whereSlice, $boolean = 'and')
    {
        $whereGroup = $this->getQuery()->forNestedWhere();

        $whereGroup->wheres = $whereSlice;

        return ['type' => 'Nested', 'query' => $whereGroup, 'boolean' => $boolean];
    }

    /**
     * Set the relations that should be eager loaded.
     * Here, all we're really doing is passing these through to this
     * query's tree so that they'll be loaded when we tell our tree to render.
     *
     * @param  mixed  $relations
     * @return $this
     */
    public function with($relations)
    {
        $this->getTree()
            ->addLoads(is_string($relations) ? func_get_args() : $relations);

        return $this;
    }

    /**
     * Prevent the specified relations from being eager loaded.
     *
     * @param  mixed  $relations
     * @return $this
     */
    public function without($relations)
    {
        $this->getTree()->remove(is_string($relations) ? func_get_args() : $relations);

        return $this;
    }

    /**
     * Chunk the results of the query.
     *
     * @param  int  $count
     * @param  callable  $callback
     * @return bool
     */
    public function chunk($count, callable $callback)
    {
        $this->enforceOrderBy();

        $page = 1;

        do {
            // We'll execute the query for the given page and get the results. If there are
            // no results we can just break and return from here. When there are results
            // we will call the callback with the current chunk of these results here.
            $results = $this->forPage($page, $count)->get();

            $countResults = $results->count();

            if ($countResults == 0) {
                break;
            }

            // On each chunk result set, we will pass them to the callback and then let the
            // developer take care of everything within the callback, which allows us to
            // keep the memory low for spinning through large result sets for working.
            if ($callback($results) === false) {
                return false;
            }

            $page++;
        } while ($countResults == $count);

        return true;
    }

    /**
     * Chunk the results of a query by comparing numeric IDs.
     *
     * @param  int  $count
     * @param  callable  $callback
     * @param  string  $column
     * @param  string|null  $alias
     * @return bool
     */
    public function chunkById($count, callable $callback, $column = null, $alias = null)
    {
        $column = is_null($column) ? $this->getMapper()->getPrimaryKeyName() : $column;

        $alias = is_null($alias) ? $column : $alias;

        $lastId = 0;

        do {
            $clone = clone $this;

            // We'll execute the query for the given page and get the results. If there are
            // no results we can just break and return from here. When there are results
            // we will call the callback with the current chunk of these results here.
            $results = $clone->forPageAfterId($count, $lastId, $column)->get();

            $countResults = $results->count();

            if ($countResults == 0) {
                break;
            }

            // On each chunk result set, we will pass them to the callback and then let the
            // developer take care of everything within the callback, which allows us to
            // keep the memory low for spinning through large result sets for working.
            if ($callback($results) === false) {
                return false;
            }

            $lastId = $results->last()->{$alias};
        } while ($countResults == $count);

        return true;
    }

    /**
     * Return the load tree for this query.
     *
     * @return Tree
     */
    public function getTree() : Tree
    {
        if (!$this->tree) {
            $this->tree = new Tree($this->mapper);
        }

        return $this->tree;
    }

    /**
     * Add a generic "order by" clause if the query doesn't already have one.
     *
     * @return void
     */
    protected function enforceOrderBy()
    {
        if (empty($this->query->orders) && empty($this->query->unionOrders)) {
            $this->orderBy($this->model->getQualifiedKeyName(), 'asc');
        }
    }

    /**
     * Execute a callback over each item while chunking.
     *
     * @param  callable  $callback
     * @param  int  $count
     * @return bool
     */
    public function each(callable $callback, $count = 1000)
    {
        return $this->chunk($count, function ($results) use ($callback) {
            foreach ($results as $key => $value) {
                if ($callback($value, $key) === false) {
                    return false;
                }
            }
        });
    }

    /**
     * Get an array with the values of a given column.
     *
     * @param  string  $column
     * @param  string|null  $key
     * @return \Illuminate\Support\Collection
     */
    public function pluck($column, $key = null)
    {
        $results = $this->toBase()->pluck($column, $key);

        // If the model has a mutator for the requested column, we will spin through
        // the results and mutate the values so that the mutated version of these
        // columns are returned as you would expect from these Eloquent models.
        if (! $this->model->hasGetMutator($column) &&
            ! $this->model->hasCast($column) &&
            ! in_array($column, $this->model->getDates())) {
            return $results;
        }

        return $results->map(function ($value) use ($column) {
            return $this->model->newFromBuilder([$column => $value])->{$column};
        });
    }

    /**
     * Paginate the given query.
     *
     * @param  int  $perPage
     * @param  array  $columns
     * @param  string  $pageName
     * @param  int|null  $page
     * @return LengthAwarePaginatorContract
     *
     * @throws \InvalidArgumentException
     */
    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null) : LengthAwarePaginatorContract
    {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $perPage = $perPage ?: $this->mapper->getPerPage();

        $results = ($total = $this->toBase()->getCountForPagination())
                                    ? $this->forPage($page, $perPage)->get($columns)
                                    : $this->mapper->newCollection();

        return new LengthAwarePaginator($results, $total, $perPage, $page, [
            'path'     => Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ]);
    }

    /**
     * Paginate the given query into a simple paginator.
     *
     * @param  int  $perPage
     * @param  array  $columns
     * @param  string  $pageName
     * @param  int|null  $page
     * @return PaginatorContract
     */
    public function simplePaginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null) : PaginatorContract
    {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $perPage = $perPage ?: $this->mapper->getPerPage();

        // Next we will set the limit and offset for this query so that when we get the
        // results we get the proper section of results. Then, we'll create the full
        // paginator instances for these results with the given page and per page.
        $this->skip(($page - 1) * $perPage)->take($perPage + 1);

        return new Paginator($this->get($columns), $perPage, $page, [
            'path'     => Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ]);
    }

    /**
     * Get the underlying query builder instance.
     *
     * @return QueryBuilder
     */
    public function getQuery() : QueryBuilder
    {
        return $this->query;
    }

    /**
     * Set the underlying query builder instance.
     *
     * @param  QueryBuilder $query
     * @return $this
     */
    public function setQuery(QueryBuilder $query)
    {
        $this->query = $query;

        return $this;
    }

    /**
     * Get a base query builder instance.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function toBase() : QueryBuilder
    {
        return $this->applyScopes()->getQuery();
    }

    /**
     * Register a new global scope.
     *
     * @param  string  $identifier
     * @param  \Illuminate\Database\Eloquent\Scope|\Closure  $scope
     * @return $this
     */
    public function withGlobalScope($identifier, $scope) : Builder
    {
        $this->scopes[$identifier] = $scope;

        if (method_exists($scope, 'extend')) {
            $scope->extend($this);
        }

        return $this;
    }

    /**
     * Remove a registered global scope.
     *
     * @param  \Illuminate\Database\Eloquent\Scope|string  $scope
     * @return $this
     */
    public function withoutGlobalScope($scope) : Builder
    {
        if (! is_string($scope)) {
            $scope = get_class($scope);
        }

        unset($this->scopes[$scope]);

        $this->removedScopes[] = $scope;

        return $this;
    }

    /**
     * Remove all or passed registered global scopes.
     *
     * @param  array|null  $scopes
     * @return $this
     */
    public function withoutGlobalScopes(array $scopes = null) : Builder
    {
        if (is_array($scopes)) {
            foreach ($scopes as $scope) {
                $this->withoutGlobalScope($scope);
            }
        } else {
            $this->scopes = [];
        }

        return $this;
    }

    /**
     * Get an array of global scopes that were removed from the query.
     *
     * @return array
     */
    public function removedScopes() : array
    {
        return $this->removedScopes;
    }

    /**
     * Delete a record from the database.
     *
     * @return mixed
     */
    public function delete()
    {
        if (isset($this->onDelete)) {
            return call_user_func($this->onDelete, $this);
        }

        return $this->toBase()->delete();
    }

    /**
     * Run the default delete function on the builder.
     *
     * Since we do not apply scopes here, the row will actually be deleted.
     *
     * @return mixed
     */
    public function forceDelete()
    {
        return $this->query->delete();
    }

    /**
     * Register a replacement for the default delete function.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public function onDelete(Closure $callback)
    {
        $this->onDelete = $callback;
    }
}