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
use Symfony\Component\TypeInfo\Exception\LogicException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 *
 * @template T of ObjectType|GenericType<ObjectType>|CollectionType<GenericType<ObjectType>>
 * @implements CompositeTypeInterface<T>
 *
 * @experimental
 */
final class IntersectionType extends Type implements CompositeTypeInterface
{
    /**
     * @use CompositeTypeTrait<T>
     */
    use CompositeTypeTrait;

    /**
     * @param list<T> $types
     */
    public function __construct(Type ...$types)
    {
        if (\count($types) < 2) {
            throw new InvalidArgumentException(\sprintf('"%s" expects at least 2 types.', self::class));
        }
        // Only accept non-composite object types, except the builtin 'object'
        foreach ($types as $t) {
            if ($t instanceof CompositeTypeInterface || $t instanceof BuiltinType || TypeIdentifier::OBJECT !== $t->getTypeIdentifier()) {
                throw new InvalidArgumentException(\sprintf('Cannot set type "%s" as a "%s" part.', $t, self::class));
            }
        }
        // All subtypes are class names and are sorted alphabetically
        usort($types, fn (Type $a, Type $b): int => (string) $a <=> (string) $b);

        $this->types = array_values(array_unique($types));
    }

    public function getTypeIdentifier(): TypeIdentifier
    {
        return TypeIdentifier::OBJECT;
    }

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
        throw new LogicException(\sprintf('Cannot get base type on "%s" compound type.', $this));
    }
}
