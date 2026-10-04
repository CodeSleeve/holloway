<?php

declare(strict_types=1);

namespace CodeSleeve\Holloway\PhpStan\Extension;

use CodeSleeve\Holloway\Builder;
use CodeSleeve\Holloway\Mapper;
use CodeSleeve\Holloway\SoftDeletes;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;

/**
 * Teaches PHPStan about the methods that Holloway resolves at runtime through __call():
 *
 *   - Local scopes: scopeSearch($query, $term) on a mapper is called as search($term), on the
 *     mapper or on a query builder for it.
 *   - Soft-delete macros: withTrashed(), withoutTrashed() and onlyTrashed() on mappers that
 *     use the SoftDeletes trait.
 *
 * A Builder knows its mapper through its second template type (Builder<TEntity, TMapper>),
 * which MapperReturnTypeExtension sets wherever a builder comes from a concrete mapper.
 */
final class ScopeMethodsExtension implements MethodsClassReflectionExtension
{
    private const SOFT_DELETE_MACROS = ['withTrashed', 'withoutTrashed', 'onlyTrashed'];

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
        private readonly EntityTypeResolver $entityTypeResolver,
    ) {}

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        return $this->resolve($classReflection, $methodName) !== null;
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        $method = $this->resolve($classReflection, $methodName);

        if ($method === null) {
            throw new \LogicException("Method {$methodName}() is not a Holloway scope or macro.");
        }

        return $method;
    }

    private function resolve(ClassReflection $classReflection, string $methodName): ?ScopeMethodReflection
    {
        if ($classReflection->getName() === Builder::class || $classReflection->isSubclassOf(Builder::class)) {
            $templates = $classReflection->getActiveTemplateTypeMap();
            $entityType = $templates->getType('TEntity');
            $mapperType = $templates->getType('TMapper');

            if ($entityType === null || $mapperType === null) {
                return null;
            }

            $mapperClassNames = $mapperType->getObjectClassNames();

            // Without exactly one concrete mapper (the default, plain Mapper, or a union) there are
            // no scopes to find.
            if (count($mapperClassNames) !== 1 || $mapperClassNames[0] === Mapper::class) {
                return null;
            }

            $mapperClassName = $mapperClassNames[0];
        } elseif ($classReflection->isSubclassOf(Mapper::class)) {
            $mapperClassName = $classReflection->getName();
            $entityType = $this->entityTypeResolver->resolve($mapperClassName) ?? new MixedType();
            $mapperType = new ObjectType($mapperClassName);
        } else {
            return null;
        }

        if (!$this->reflectionProvider->hasClass($mapperClassName)) {
            return null;
        }

        $mapper = $this->reflectionProvider->getClass($mapperClassName);
        $returnType = new GenericObjectType(Builder::class, [$entityType, $mapperType]);
        $scopeName = 'scope' . ucfirst($methodName);

        if ($mapper->hasNativeMethod($scopeName)) {
            $scope = $mapper->getNativeMethod($scopeName);

            if ($scope->isPublic()) {
                return new ScopeMethodReflection($classReflection, $methodName, $scope, $returnType);
            }
        }

        if (in_array($methodName, self::SOFT_DELETE_MACROS, true) && $mapper->hasTraitUse(SoftDeletes::class)) {
            return new ScopeMethodReflection($classReflection, $methodName, null, $returnType);
        }

        return null;
    }
}
