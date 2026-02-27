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

use Symfony\Component\TypeInfo\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Represents a generic type, which is a type that holds variable parts.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 *
 * @template T of BuiltinType<TypeIdentifier::ARRAY>|BuiltinType<TypeIdentifier::ITERABLE>|ObjectType
 *
 * @implements WrappingTypeInterface<T>
 */
final class GenericType extends Type implements WrappingTypeInterface
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
        if ($type instanceof BuiltinType && TypeIdentifier::ARRAY !== $type->getTypeIdentifier() && TypeIdentifier::ITERABLE !== $type->getTypeIdentifier()) {
            throw new InvalidArgumentException(\sprintf('Cannot create "%s" with "%s" type.', self::class, $type));
        }

        $this->variableTypes = $variableTypes;
    }

    public function getWrappedType(): Type
    {
        return $this->type;
    }

    /**
     * @return list<Type>
     */
    public function getVariableTypes(): array
    {
        return $this->variableTypes;
    }

    public function wrappedTypeIsSatisfiedBy(callable $specification): bool
    {
        return $this->getWrappedType()->isSatisfiedBy($specification);
    }

    public function __toString(): string
    {
        $typeString = (string) $this->type;

        $variableTypesString = '';
        $glue = '';
        foreach ($this->variableTypes as $t) {
            $variableTypesString .= $glue.$t;
            $glue = ', ';
        }

        return $typeString.'<'.$variableTypesString.'>';
    }
}
