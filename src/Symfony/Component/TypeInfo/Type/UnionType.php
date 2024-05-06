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
 * @template T of Type
 *
 * @experimental
 */
final class UnionType extends Type
{
    /**
     * @use CompositeTypeTrait<T>
     */
    use CompositeTypeTrait;

    public function is(callable $callable): bool
    {
        return $this->atLeastOneTypeIs($callable);
    }

    /**
     * @throws LogicException
     */
    public function getBaseType(): BuiltinType|ObjectType
    {
        $nonNullableType = $this->asNonNullable();
        if (!$nonNullableType instanceof self) {
            return $nonNullableType->getBaseType();
        }

        throw new LogicException(sprintf('Cannot get base type on "%s" compound type.', $this));
    }

    public function asNonNullable(): Type
    {
        $nonNullableTypes = [];
        foreach ($this->getTypes() as $type) {
            if ($type->isA(TypeIdentifier::NULL)) {
                continue;
            }

            $nonNullableType = $type->asNonNullable();
            $nonNullableTypes = [
                ...$nonNullableTypes,
                ...($nonNullableType instanceof self ? $nonNullableType->getTypes() : [$nonNullableType]),
            ];
        }

        return \count($nonNullableTypes) > 1 ? new self(...$nonNullableTypes) : $nonNullableTypes[0];
    }

    public function __toString(): string
    {
        $string = '';
        $glue = '';

        foreach ($this->types as $t) {
            $string .= $glue.($t instanceof IntersectionType ? '('.((string) $t).')' : ((string) $t));
            $glue = '|';
        }

        return $string;
    }
}
