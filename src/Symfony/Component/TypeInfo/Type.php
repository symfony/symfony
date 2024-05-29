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
 *
 * @experimental
 */
abstract class Type implements \Stringable
{
    use TypeFactoryTrait;

    abstract public function getBaseType(): BuiltinType|ObjectType;

    /**
     * @param TypeIdentifier|class-string $subject
     */
    abstract public function isA(TypeIdentifier|string $subject): bool;

    abstract public function asNonNullable(): self;

    /**
     * @param callable(Type): bool $callable
     */
    public function is(callable $callable): bool
    {
        return $callable($this);
    }

    public function isNullable(): bool
    {
        return $this->is(fn (Type $t): bool => $t->isA(TypeIdentifier::NULL) || $t->isA(TypeIdentifier::MIXED));
    }
}
