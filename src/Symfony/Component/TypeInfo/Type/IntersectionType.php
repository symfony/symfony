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

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 *
 * @template T of ObjectType|GenericType<ObjectType>|CollectionType<GenericType<ObjectType>>
 *
 * @implements CompositeTypeInterface<T>
 */
final class IntersectionType extends Type implements CompositeTypeInterface
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
            throw new InvalidArgumentException(\sprintf('"%s" expects at least 2 types.', self::class));
        }

        foreach ($types as $type) {
            if ($type instanceof CompositeTypeInterface || $type instanceof NullableType) {
                throw new InvalidArgumentException(\sprintf('Cannot set "%s" as a "%s" part.', $type, self::class));
            }

            while ($type instanceof WrappingTypeInterface) {
                $type = $type->getWrappedType();
            }

            if (!$type instanceof ObjectType) {
                throw new InvalidArgumentException(\sprintf('Cannot set "%s" as a "%s" part.', $type, self::class));
            }
        }

        usort($types, fn (Type $a, Type $b): int => (string) $a <=> (string) $b);
        $this->types = array_values(array_unique($types));
    }

    /**
     * @return list<T>
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    public function composedTypesAreSatisfiedBy(callable $specification): bool
    {
        foreach ($this->types as $type) {
            if (!$type->isSatisfiedBy($specification)) {
                return false;
            }
        }

        return true;
    }

    public function __toString(): string
    {
        $string = '';
        $glue = '';

        foreach ($this->types as $t) {
            $string .= $glue.($t instanceof CompositeTypeInterface ? '('.$t.')' : $t);
            $glue = '&';
        }

        return $string;
    }
}
