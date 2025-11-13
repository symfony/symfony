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
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 *
 * @template T of TypeIdentifier
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

    /**
     * @return T
     */
    public function getTypeIdentifier(): TypeIdentifier
    {
        return $this->typeIdentifier;
    }

    public function isIdentifiedBy(TypeIdentifier|string ...$identifiers): bool
    {
        foreach ($identifiers as $identifier) {
            if ($identifier === $this->typeIdentifier || $identifier === $this->typeIdentifier->value) {
                return true;
            }
        }

        return false;
    }

    public function isNullable(): bool
    {
        return \in_array($this->typeIdentifier, [TypeIdentifier::NULL, TypeIdentifier::MIXED], true);
    }

    public function accepts(mixed $value): bool
    {
        return match ($this->typeIdentifier) {
            TypeIdentifier::ARRAY => \is_array($value),
            TypeIdentifier::BOOL => \is_bool($value),
            TypeIdentifier::CALLABLE => \is_callable($value),
            TypeIdentifier::FALSE => false === $value,
            TypeIdentifier::FLOAT => \is_float($value),
            TypeIdentifier::INT => \is_int($value),
            TypeIdentifier::ITERABLE => is_iterable($value),
            TypeIdentifier::MIXED => true,
            TypeIdentifier::NULL => null === $value,
            TypeIdentifier::OBJECT => \is_object($value),
            TypeIdentifier::RESOURCE => \is_resource($value),
            TypeIdentifier::STRING => \is_string($value),
            TypeIdentifier::TRUE => true === $value,
            default => false,
        };
    }

    public function __toString(): string
    {
        return $this->typeIdentifier->value;
    }
}
