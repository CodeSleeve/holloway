<?php

declare(strict_types=1);

namespace CodeSleeve\Holloway\PhpStan\Extension;

use PHPStan\Reflection\Assertions;
use PHPStan\Reflection\ClassMemberReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ExtendedFunctionVariant;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\ExtendedParametersAcceptor;
use PHPStan\PhpDoc\ResolvedPhpDocBlock;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\Type;

/**
 * A method that exists at runtime but not in the source: a local scope called without its
 * "scope" prefix (scopeActive($query, ...) is called as active(...)), or a soft-delete macro.
 *
 * Scopes reuse the parameters of the real scopeXxx() method minus the leading $query; macros
 * take no parameters. Both return a Builder.
 */
final class ScopeMethodReflection implements ExtendedMethodReflection
{
    private ExtendedParametersAcceptor $variant;

    public function __construct(
        private readonly ClassReflection $declaringClass,
        private readonly string $name,
        ?ExtendedMethodReflection $scopeMethod,
        Type $returnType,
    ) {
        if ($scopeMethod === null) {
            $this->variant = new ExtendedFunctionVariant(
                TemplateTypeMap::createEmpty(),
                null,
                [],
                false,
                $returnType,
                $returnType,
                $returnType,
            );

            return;
        }

        $original = $scopeMethod->getOnlyVariant();

        $this->variant = new ExtendedFunctionVariant(
            $original->getTemplateTypeMap(),
            $original->getResolvedTemplateTypeMap(),
            array_slice($original->getParameters(), 1),   // drop the leading $query
            $original->isVariadic(),
            $returnType,
            $returnType,
            $returnType,
            $original->getCallSiteVarianceMap(),
        );
    }

    public function getDeclaringClass(): ClassReflection
    {
        return $this->declaringClass;
    }

    public function isStatic(): bool
    {
        return false;
    }

    public function isPrivate(): bool
    {
        return false;
    }

    public function isPublic(): bool
    {
        return true;
    }

    public function getDocComment(): ?string
    {
        return null;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrototype(): ClassMemberReflection
    {
        return $this;
    }

    public function getVariants(): array
    {
        return [$this->variant];
    }

    public function getOnlyVariant(): ExtendedParametersAcceptor
    {
        return $this->variant;
    }

    public function getNamedArgumentsVariants(): ?array
    {
        return null;
    }

    public function acceptsNamedArguments(): TrinaryLogic
    {
        return TrinaryLogic::createYes();
    }

    public function getAsserts(): Assertions
    {
        return Assertions::createEmpty();
    }

    public function getSelfOutType(): ?Type
    {
        return null;
    }

    public function returnsByReference(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function isDeprecated(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function getDeprecatedDescription(): ?string
    {
        return null;
    }

    public function isFinal(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function isFinalByKeyword(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function isInternal(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function isAbstract(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function isBuiltin(): bool
    {
        return false;
    }

    public function getThrowType(): ?Type
    {
        return null;
    }

    public function hasSideEffects(): TrinaryLogic
    {
        return TrinaryLogic::createMaybe();
    }

    public function isPure(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function getPureUnlessCallableIsImpureParameters(): array
    {
        return [];
    }

    public function getAttributes(): array
    {
        return [];
    }

    public function mustUseReturnValue(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function getResolvedPhpDoc(): ?ResolvedPhpDocBlock
    {
        return null;
    }
}
