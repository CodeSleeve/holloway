<?php

declare(strict_types=1);

namespace CodeSleeve\Holloway\PhpStan\Extension;

use CodeSleeve\Holloway\Holloway;
use CodeSleeve\Holloway\Mapper;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
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

        $argValue = $args[0]->value;

        // Foo::class, self::class, static::class, parent::class
        if (
            $argValue instanceof ClassConstFetch
            && $argValue->name instanceof Identifier
            && $argValue->name->name === 'class'
            && $argValue->class instanceof Name
        ) {
            $nameStr = (string) $argValue->class;

            if (in_array($nameStr, ['self', 'static'], true)) {
                $classReflection = $scope->getClassReflection();
                if ($classReflection === null) {
                    return new ObjectType(Mapper::class);
                }
                $entityClass = $classReflection->getName();
            } elseif ($nameStr === 'parent') {
                $classReflection = $scope->getClassReflection();
                $parentClass = $classReflection?->getParentClass();
                if ($parentClass === null) {
                    return new ObjectType(Mapper::class);
                }
                $entityClass = $parentClass->getName();
            } else {
                $entityClass = $nameStr;
            }

            return new GenericObjectType(Mapper::class, [new ObjectType($entityClass)]);
        }

        // Variable typed as a constant string (e.g. $entityClass = Foo::class passed as arg)
        $argType = $scope->getType($argValue);
        $constantStrings = $argType->getConstantStrings();
        if ($constantStrings !== []) {
            return new GenericObjectType(Mapper::class, [new ObjectType($constantStrings[0]->getValue())]);
        }

        return new ObjectType(Mapper::class);
    }
}
