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
 *
 * @experimental
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

    public function getBaseType(): BuiltinType|self
    {
        return $this;
    }

    public function getTypeIdentifier(): TypeIdentifier
    {
        return TypeIdentifier::OBJECT;
    }

    public function isA(TypeIdentifier|string $subject): bool
    {
        if ($subject instanceof TypeIdentifier) {
            return $this->getTypeIdentifier() === $subject;
        }

        return is_a($this->getClassName(), $subject, allow_string: true);
    }

    /**
     * @return T
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    public function asNonNullable(): static
    {
        return $this;
    }

    public function __toString(): string
    {
        return $this->className;
    }
}
