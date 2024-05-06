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
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 *
 * @internal
 *
 * @template T of Type
 */
trait CompositeTypeTrait
{
    /**
     * @var list<T>
     */
    private readonly array $types;

    /**
     * @param list<T> $types
     */
    public function __construct(Type ...$types)
    {
        if (\count($types) < 2) {
            throw new InvalidArgumentException(sprintf('"%s" expects at least 2 types.', self::class));
        }

        foreach ($types as $t) {
            if ($t instanceof self) {
                throw new InvalidArgumentException(sprintf('Cannot set "%s" as a "%1$s" part.', self::class));
            }
        }

        usort($types, fn (Type $a, Type $b): int => (string) $a <=> (string) $b);
        $this->types = array_values(array_unique($types));
    }

    public function isA(TypeIdentifier|string $subject): bool
    {
        return $this->is(fn (Type $type) => $type->isA($subject));
    }

    /**
     * @return list<T>
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    /**
     * @param callable(T): bool $callable
     */
    public function atLeastOneTypeIs(callable $callable): bool
    {
        foreach ($this->types as $t) {
            if ($callable($t)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param callable(T): bool $callable
     */
    public function everyTypeIs(callable $callable): bool
    {
        foreach ($this->types as $t) {
            if (!$callable($t)) {
                return false;
            }
        }

        return true;
    }
}
