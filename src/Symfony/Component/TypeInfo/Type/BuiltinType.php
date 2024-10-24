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
            if (\is_string($identifier)) {
                try {
                    $identifier = TypeIdentifier::from($identifier);
                } catch (\ValueError) {
                    continue;
                }
            }

            if ($identifier === $this->typeIdentifier) {
                return true;
            }
        }

        return false;
    }

    public function isNullable(): bool
    {
        return \in_array($this->typeIdentifier, [TypeIdentifier::NULL, TypeIdentifier::MIXED]);
    }

    public function __toString(): string
    {
        return $this->typeIdentifier->value;
    }
}
