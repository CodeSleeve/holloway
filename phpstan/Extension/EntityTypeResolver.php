<?php

declare(strict_types=1);

namespace CodeSleeve\Holloway\PhpStan\Extension;

use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

/**
 * Resolves the entity type of a concrete mapper by reading the default value of its
 * $entityClassName property (walking up the parent classes), e.g.:
 *
 *   protected string $entityClassName = Pup::class;
 */
final class EntityTypeResolver
{
    public function __construct(
        private readonly ReflectionProvider $reflectionProvider
    ) {}

    public function resolve(string $mapperClass): ?Type
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
}
