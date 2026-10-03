<?php

declare(strict_types=1);

namespace CodeSleeve\Holloway\PhpStan\Extension;

use CodeSleeve\Holloway\Builder;
use CodeSleeve\Holloway\Mapper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * Resolves the entity type for all Mapper query methods without requiring
 * an explicit type annotation (e.g. "extends Mapper<Entity>") on concrete
 * mapper classes.
 *
 * Strategy: read the default value of $entityClassName from the concrete mapper
 * class using PHPStan's reflection API. Since Holloway mappers always declare:
 *
 *   protected string $entityClassName = Pup::class;
 *
 * we can recover the entity type at PHPStan analysis time and return properly
 * typed results — e.g. Pup|null for find(), Collection<int, Pup> for get().
 *
 * Covered methods (entity-returning):
 *   find, findOrFail, first, firstOrFail, findMany, get, all, paginate,
 *   simplePaginate, makeEntity, makeEntities, instantiateEntity, newCollection
 *
 * Covered methods (builder-returning):
 *   newQuery, newQueryWithoutScopes, newQueryWithoutScope, newHollowayBuilder,
 *   query, with, where, orWhere, whereKey, whereIn, whereNull,
 *   whereNotNull, whereBetween, orderBy, orderByDesc, take, skip, limit,
 *   offset, forPage, select, addSelect, distinct, groupBy, having, join,
 *   leftJoin, rightJoin, withCount, without, scopes, applyScopes,
 *   withGlobalScope, withoutGlobalScope, withoutGlobalScopes
 */
final class MapperReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    /** Methods that return a single entity or null */
    private const SINGLE_OR_NULL = ['find', 'first'];

    /** Methods that always return a single entity (throw on miss) */
    private const SINGLE_REQUIRED = ['findOrFail', 'firstOrFail'];

    /** Methods that return a single entity */
    private const ENTITY = ['makeEntity', 'instantiateEntity'];

    /** Methods that return Collection<int, TEntity> */
    private const COLLECTION = ['findMany', 'get', 'all', 'makeEntities', 'newCollection'];

    /** Methods that return LengthAwarePaginator<int, TEntity> */
    private const LENGTH_AWARE_PAGINATOR = ['paginate'];

    /** Methods that return Paginator<int, TEntity> */
    private const SIMPLE_PAGINATOR = ['simplePaginate'];

    /** Methods that return Builder<TEntity> */
    private const BUILDER = [
        'newQuery', 'newQueryWithoutScopes', 'newQueryWithoutScope', 'newHollowayBuilder',
        'query', 'with', 'without', 'withCount',
        'where', 'orWhere', 'whereKey', 'whereIn', 'whereNull', 'whereNotNull',
        'whereBetween', 'orderBy', 'orderByDesc', 'take', 'skip', 'limit',
        'offset', 'forPage', 'select', 'addSelect', 'distinct',
        'groupBy', 'having', 'join', 'leftJoin', 'rightJoin',
        'scopes', 'applyScopes', 'withGlobalScope', 'withoutGlobalScope',
        'withoutGlobalScopes',
    ];

    private const ALL_METHODS = [
        ...self::SINGLE_OR_NULL,
        ...self::SINGLE_REQUIRED,
        ...self::ENTITY,
        ...self::COLLECTION,
        ...self::LENGTH_AWARE_PAGINATOR,
        ...self::SIMPLE_PAGINATOR,
        ...self::BUILDER,
    ];

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider
    ) {}

    public function getClass(): string
    {
        return Mapper::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return in_array($methodReflection->getName(), self::ALL_METHODS, true);
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope
    ): ?Type {
        $callerType = $scope->getType($methodCall->var);

        if (!$callerType->isObject()->yes()) {
            return null;
        }

        $classNames = $callerType->getObjectClassNames();
        if ($classNames === []) {
            return null;
        }

        $entityType = $this->resolveEntityType($classNames[0]);

        if ($entityType === null) {
            return null;
        }

        return $this->buildReturnType($methodReflection->getName(), $entityType, $methodCall, $scope);
    }

    private function resolveEntityType(string $mapperClass): ?Type
    {
        if (!$this->reflectionProvider->hasClass($mapperClass)) {
            return null;
        }

        $class = $this->reflectionProvider->getClass($mapperClass);

        do {
            $defaults = $class->getNativeReflection()->getDefaultProperties();
            if (isset($defaults['entityClassName'])
                && is_string($defaults['entityClassName'])
                && $defaults['entityClassName'] !== ''
            ) {
                return new ObjectType($defaults['entityClassName']);
            }
            $class = $class->getParentClass();
        } while ($class !== null);

        return null;
    }

    private function buildReturnType(string $method, Type $entityType, MethodCall $methodCall, Scope $scope): Type
    {
        $collection = new GenericObjectType(Collection::class, [new IntegerType(), $entityType]);

        if ($method === 'find') {
            // find(array $ids) returns Collection<int, TEntity>; find(scalar) returns TEntity|null.
            // When the argument could be either, the result is the union of both.
            $args = $methodCall->getArgs();
            $nullable = TypeCombinator::addNull($entityType);

            if ($args === []) {
                return $nullable;
            }

            $isArray = $scope->getType($args[0]->value)->isArray();

            if ($isArray->yes()) {
                return $collection;
            }

            return $isArray->no() ? $nullable : TypeCombinator::union($collection, $nullable);
        }

        if (in_array($method, self::SINGLE_OR_NULL, true)) {
            return TypeCombinator::addNull($entityType);
        }

        if (in_array($method, [...self::SINGLE_REQUIRED, ...self::ENTITY], true)) {
            return $entityType;
        }

        if (in_array($method, self::COLLECTION, true)) {
            return $collection;
        }

        if (in_array($method, self::LENGTH_AWARE_PAGINATOR, true)) {
            return new GenericObjectType(LengthAwarePaginator::class, [new IntegerType(), $entityType]);
        }

        if (in_array($method, self::SIMPLE_PAGINATOR, true)) {
            return new GenericObjectType(Paginator::class, [new IntegerType(), $entityType]);
        }

        if (in_array($method, self::BUILDER, true)) {
            return new GenericObjectType(Builder::class, [$entityType]);
        }

        return new MixedType();
    }
}
