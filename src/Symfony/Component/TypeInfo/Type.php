<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo;

use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\ObjectType;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 */
abstract class Type implements \Stringable
{
    use TypeFactoryTrait;

    abstract public function getBaseType(): BuiltinType|ObjectType;

    /**
     * @param callable(Type): bool $callable
     */
    public function is(callable $callable): bool
    {
        return $callable($this);
    }

    public function isA(TypeIdentifier $typeIdentifier): bool
    {
        return $this->getBaseType()->getTypeIdentifier() === $typeIdentifier;
    }

    public function isNullable(): bool
    {
        return \in_array($this->getBaseType()->getTypeIdentifier(), [TypeIdentifier::NULL, TypeIdentifier::MIXED], true);
    }

    abstract public function asNonNullable(): self;
}
