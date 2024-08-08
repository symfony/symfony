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

    /**
     * @param callable(T): bool $callable
     * @return list<T>
     */
    public function filter(callable $callable): array
    {
        return $this->filterTypes($callable, ...$this->getTypes());
    }

    /**
     * @param callable(T): bool $callable
     * @param array<T>          $types
     * @return list<T>
     */
    private function filterTypes(callable $callable, Type ...$types): array
    {
        return array_values(array_filter($types, $callable));
    }
}
