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
 * @template T of Type
 *
 * @implements CompositeTypeInterface<T>
 */
class UnionType extends Type implements CompositeTypeInterface
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
            if ($type instanceof self) {
                throw new InvalidArgumentException(\sprintf('Cannot set "%s" as a "%1$s" part.', self::class));
            }

            if ($type instanceof BuiltinType) {
                if (TypeIdentifier::NULL === $type->getTypeIdentifier() && !is_a(static::class, NullableType::class, allow_string: true)) {
                    throw new InvalidArgumentException(\sprintf('Cannot create union with "null", please use "%s" instead.', NullableType::class));
                }

                if ($type->getTypeIdentifier()->isStandalone()) {
                    throw new InvalidArgumentException(\sprintf('Cannot create union with "%s" standalone type.', $type));
                }
            }
        }

        usort($types, fn (Type $a, Type $b): int => (string) $a <=> (string) $b);
        $this->types = array_values(array_unique($types));

        $builtinTypesIdentifiers = array_map(
            fn (BuiltinType $t): TypeIdentifier => $t->getTypeIdentifier(),
            array_filter($this->types, fn (Type $t): bool => $t instanceof BuiltinType),
        );

        if ((\in_array(TypeIdentifier::TRUE, $builtinTypesIdentifiers, true) || \in_array(TypeIdentifier::FALSE, $builtinTypesIdentifiers, true)) && \in_array(TypeIdentifier::BOOL, $builtinTypesIdentifiers, true)) {
            throw new InvalidArgumentException('Cannot create union with redundant boolean type.');
        }

        if (\in_array(TypeIdentifier::TRUE, $builtinTypesIdentifiers, true) && \in_array(TypeIdentifier::FALSE, $builtinTypesIdentifiers, true)) {
            throw new InvalidArgumentException('Cannot create union with both "true" and "false", "bool" should be used instead.');
        }

        if (\in_array(TypeIdentifier::OBJECT, $builtinTypesIdentifiers, true) && \count(array_filter($this->types, fn (Type $t): bool => $t instanceof ObjectType))) {
            throw new InvalidArgumentException('Cannot create union with both "object" and class type.');
        }
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
            if ($type->isSatisfiedBy($specification)) {
                return true;
            }
        }

        return false;
    }

    public function __toString(): string
    {
        $string = '';
        $glue = '';

        foreach ($this->types as $t) {
            $string .= $glue.($t instanceof CompositeTypeInterface ? '('.$t.')' : $t);
            $glue = '|';
        }

        return $string;
    }
}
