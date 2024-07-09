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

use Symfony\Component\TypeInfo\Exception\LogicException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 *
 * @template T of TypeIdentifier
 *
 * @experimental
 */
final class BuiltinType extends Type
{
    /**
     * @param T $typeIdentifier
     */
    public function __construct(
        private readonly TypeIdentifier $typeIdentifier,
    ) {
    }

    public function getBaseType(): self|ObjectType
    {
        return $this;
    }

    /**
     * @return T
     */
    public function getTypeIdentifier(): TypeIdentifier
    {
        return $this->typeIdentifier;
    }

    public function isA(TypeIdentifier|string $subject): bool
    {
        if ($subject instanceof TypeIdentifier) {
            return $this->getTypeIdentifier() === $subject;
        }

        try {
            return TypeIdentifier::from($subject) === $this->getTypeIdentifier();
        } catch (\ValueError) {
            return false;
        }
    }

    /**
     * @return self|UnionType<BuiltinType<TypeIdentifier::OBJECT>|BuiltinType<TypeIdentifier::RESOURCE>|BuiltinType<TypeIdentifier::ARRAY>|BuiltinType<TypeIdentifier::STRING>|BuiltinType<TypeIdentifier::FLOAT>|BuiltinType<TypeIdentifier::INT>|BuiltinType<TypeIdentifier::BOOL>>
     */
    public function asNonNullable(): self|UnionType
    {
        if (TypeIdentifier::NULL === $this->typeIdentifier) {
            throw new LogicException('"null" cannot be turned as non nullable.');
        }

        // "mixed" is an alias of "object|resource|array|string|float|int|bool|null"
        // therefore, its non-nullable version is "object|resource|array|string|float|int|bool"
        if (TypeIdentifier::MIXED === $this->typeIdentifier) {
            return new UnionType(
                new self(TypeIdentifier::OBJECT),
                new self(TypeIdentifier::RESOURCE),
                new self(TypeIdentifier::ARRAY),
                new self(TypeIdentifier::STRING),
                new self(TypeIdentifier::FLOAT),
                new self(TypeIdentifier::INT),
                new self(TypeIdentifier::BOOL),
            );
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->typeIdentifier->value;
    }
}
