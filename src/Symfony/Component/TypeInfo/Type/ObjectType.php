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
 * @template T of class-string
 */
class ObjectType extends Type
{
    /**
     * @param T $className
     */
    public function __construct(
        private readonly string $className,
    ) {
    }

    public function getTypeIdentifier(): TypeIdentifier
    {
        return TypeIdentifier::OBJECT;
    }

    /**
     * @return T
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    public function isIdentifiedBy(TypeIdentifier|string ...$identifiers): bool
    {
        foreach ($identifiers as $identifier) {
            if ($identifier instanceof TypeIdentifier) {
                if (TypeIdentifier::OBJECT === $identifier) {
                    return true;
                }

                continue;
            }

            if (TypeIdentifier::OBJECT->value === $identifier) {
                return true;
            }

            if (is_a($this->className, $identifier, allow_string: true)) {
                return true;
            }
        }

        return false;
    }

    public function accepts(mixed $value): bool
    {
        return $value instanceof $this->className;
    }

    public function __toString(): string
    {
        return $this->className;
    }
}
