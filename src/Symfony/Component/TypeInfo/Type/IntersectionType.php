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

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 *
 * @template T of Type
 *
 * @experimental
 */
final class IntersectionType extends Type
{
    /**
     * @use CompositeTypeTrait<T>
     */
    use CompositeTypeTrait;

    public function is(callable $callable): bool
    {
        return $this->everyTypeIs($callable);
    }

    public function __toString(): string
    {
        $string = '';
        $glue = '';

        foreach ($this->types as $t) {
            $string .= $glue.($t instanceof UnionType ? '('.((string) $t).')' : ((string) $t));
            $glue = '&';
        }

        return $string;
    }

    /**
     * @throws LogicException
     */
    public function getBaseType(): BuiltinType|ObjectType
    {
        throw new LogicException(sprintf('Cannot get base type on "%s" compound type.', $this));
    }

    /**
     * @throws LogicException
     */
    public function asNonNullable(): self
    {
        if ($this->isNullable()) {
            throw new LogicException(sprintf('"%s cannot be turned as non nullable.', (string) $this));
        }

        return $this;
    }
}
