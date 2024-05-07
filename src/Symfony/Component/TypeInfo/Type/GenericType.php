<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\Type;

use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Represents a generic type, which is a type that holds variable parts.
 *
 * It proxies every method to the main type and adds methods related to variable types.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 *
 * @template T of BuiltinType<TypeIdentifier::ARRAY>|BuiltinType<TypeIdentifier::ITERABLE>|ObjectType
 *
 * @experimental
 */
final class GenericType extends Type
{
    /**
     * @var list<Type>
     */
    private readonly array $variableTypes;

    /**
     * @param T $type
     */
    public function __construct(
        private readonly BuiltinType|ObjectType $type,
        Type ...$variableTypes,
    ) {
        $this->variableTypes = $variableTypes;
    }

    public function getBaseType(): BuiltinType|ObjectType
    {
        return $this->getType();
    }

    /**
     * @return T
     */
    public function getType(): BuiltinType|ObjectType
    {
        return $this->type;
    }

    public function isA(TypeIdentifier|string $subject): bool
    {
        return $this->getType()->isA($subject);
    }

    public function asNonNullable(): self
    {
        return $this;
    }

    /**
     * @return list<Type>
     */
    public function getVariableTypes(): array
    {
        return $this->variableTypes;
    }

    public function __toString(): string
    {
        $typeString = (string) $this->type;

        $variableTypesString = '';
        $glue = '';
        foreach ($this->variableTypes as $t) {
            $variableTypesString .= $glue.((string) $t);
            $glue = ',';
        }

        return $typeString.'<'.$variableTypesString.'>';
    }

    /**
     * Proxies all method calls to the original type.
     *
     * @param list<mixed> $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->type->{$method}(...$arguments);
    }
}
