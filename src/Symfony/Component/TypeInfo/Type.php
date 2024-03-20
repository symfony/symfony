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

use Symfony\Component\TypeInfo\Exception\LogicException;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\IntersectionType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\UnionType;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 */
abstract class Type implements \Stringable
{
    use TypeFactoryTrait;

    public function getBaseType(): BuiltinType|ObjectType
    {
        if ($this instanceof UnionType || $this instanceof IntersectionType) {
            throw new LogicException(sprintf('Cannot get base type on "%s" compound type.', (string) $this));
        }

        $baseType = $this;

        if ($baseType instanceof CollectionType) {
            $baseType = $baseType->getType();
        }

        if ($baseType instanceof GenericType) {
            $baseType = $baseType->getType();
        }

        return $baseType;
    }

    /**
     * @param callable(Type): bool $callable
     */
    public function is(callable $callable): bool
    {
        return match (true) {
            $this instanceof UnionType => $this->atLeastOneTypeIs($callable),
            $this instanceof IntersectionType => $this->everyTypeIs($callable),
            default => $callable($this),
        };
    }

    public function isA(TypeIdentifier $typeIdentifier): bool
    {
        return $this->testIdentifier(fn (TypeIdentifier $i): bool => $typeIdentifier === $i);
    }

    public function isNullable(): bool
    {
        return $this->testIdentifier(fn (TypeIdentifier $i): bool => TypeIdentifier::NULL === $i || TypeIdentifier::MIXED === $i);
    }

    abstract public function asNonNullable(): self;

    /**
     * @param callable(TypeIdentifier): bool $test
     */
    private function testIdentifier(callable $test): bool
    {
        $callable = function (self $t) use ($test, &$callable): bool {
            // unwrap compound type to forward type identifier check
            if ($t instanceof UnionType || $t instanceof IntersectionType) {
                return $t->is($callable);
            }

            return $test($t->getBaseType()->getTypeIdentifier());
        };

        return $this->is($callable);
    }
}
