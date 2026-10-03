<?php

declare(strict_types=1);

namespace CodeSleeve\Holloway\PhpStan\Extension;

use CodeSleeve\Holloway\Holloway;
use CodeSleeve\Holloway\Mapper;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

/**
 * Teaches PHPStan that Holloway::getMapper(Foo::class) returns Mapper<Foo>.
 *
 * Without this extension, getMapper() returns the raw non-generic Mapper,
 * which loses entity-type information for all subsequent query calls.
 *
 * The argument may be a class constant, an entity instance, or a class-string;
 * anything PHPStan cannot narrow to a class falls back to the raw Mapper.
 *
 * Usage: include vendor/codesleeve/holloway/phpstan/extension.neon in your
 * project's phpstan.neon.
 */
final class GetMapperReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return Holloway::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'getMapper';
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope
    ): Type {
        $args = $methodCall->getArgs();

        if (count($args) === 0) {
            return new ObjectType(Mapper::class);
        }

        // Foo::class, self::class, static::class, an entity instance, or a
        // class-string<Foo> all resolve to the entity's object type here.
        $entityType = $scope->getType($args[0]->value)->getObjectTypeOrClassStringObjectType();

        if ($entityType->getObjectClassNames() === []) {
            return new ObjectType(Mapper::class);
        }

        return new GenericObjectType(Mapper::class, [$entityType]);
    }
}
