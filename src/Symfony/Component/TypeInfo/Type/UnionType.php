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
 * @template T of BuiltinType|ObjectType|GenericType|CollectionType|IntersectionType
 *
 * @experimental
 */
final class UnionType extends Type implements CompositeTypeInterface
{
    /**
     * @use CompositeTypeTrait<T>
     */
    use CompositeTypeTrait;

    private readonly TypeIdentifier $typeIdentifier;
    private readonly bool $isNullable;

    public function __construct(Type ...$types)
    {
        if (\count($types) < 2) {
            throw new InvalidArgumentException(\sprintf('"%s" expects at least 2 types.', self::class));
        }

        $nullable = false;
        $hasClassType = false;
        $hasObject = false;
        $identifiers = [];
        foreach ($types as $t) {
            if ($t instanceof self) {
                throw new InvalidArgumentException(\sprintf('Cannot set "%s" as a "%s" part.', $t, self::class));
            }
            if (!$t->getTypeIdentifier()->isComposable()) {
                throw new InvalidArgumentException(\sprintf('Type %s can only be used as a standalone type', $t->getTypeIdentifier()->value));
            }
            if (TypeIdentifier::NULL === $t->getTypeIdentifier()) {
                $nullable = true;
            }
            $hasClassType = $hasClassType || !$t instanceof BuiltinType && TypeIdentifier::OBJECT === $t->getTypeIdentifier();
            $hasObject = $hasObject || $t instanceof BuiltinType && TypeIdentifier::OBJECT === $t->getTypeIdentifier();
            $identifiers[$t->getTypeIdentifier()->name] = $t->getTypeIdentifier();
        }
        if ($hasClassType && $hasObject) {
            throw new InvalidArgumentException('Union contains both object and a class type, which is redundant.');
        }

        $types = array_values(array_unique($types));

        // bool, true and false cannot be composed together (use same errors as PHP in similar cases)
        $booleanTypes = $this->filterTypes(fn(Type $t): bool => $t->getTypeIdentifier()->isBool(), ...$types);
        if (1 < \count($booleanTypes)) {
            throw \in_array(TypeIdentifier::TRUE, $identifiers, true) && \in_array(TypeIdentifier::FALSE, $identifiers, true)
                ? new InvalidArgumentException('Union type contains both true and false, bool should be used instead.')
                : new InvalidArgumentException('Duplicate boolean type is redundant.');
        }

        $this->typeIdentifier = 1 === count($identifiers) ? current($identifiers) : TypeIdentifier::MIXED;
        $this->isNullable = $nullable;
        $this->types = $this->sortSubtypesForRendering(...$types);
    }

    public function getTypeIdentifier(): TypeIdentifier
    {
        return $this->typeIdentifier;
    }

    /**
     * @param callable(T): bool $callable
     */
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

        throw new LogicException(\sprintf('Cannot get base type on "%s" compound type.', $this));
    }

    /**
     * Whether this union represents a nullable type.
     *
     * A union is nullable if it contains "null" (as it may not contain unions or mixed).
     */
    public function isNullable(): bool
    {
        return $this->isNullable;
    }

    public function asNonNullable(): Type
    {
        if (!$this->isNullable) {
            return $this;
        }
        $nonNullableTypes = $this->filter(fn (Type $t): bool => TypeIdentifier::NULL !== $t->getTypeIdentifier());

        return 1 < \count($nonNullableTypes) ? new self(...$nonNullableTypes) : $nonNullableTypes[0];
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

    /**
     * Proxies all method calls to the original non-nullable type.
     *
     * @param list<mixed> $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        $nonNullableType = $this->asNonNullable();

        if (!$nonNullableType instanceof self) {
            if (!method_exists($nonNullableType, $method)) {
                throw new LogicException(\sprintf('Method "%s" doesn\'t exist on "%s" type.', $method, $nonNullableType));
            }

            return $nonNullableType->{$method}(...$arguments);
        }

        throw new LogicException(\sprintf('Cannot call "%s" on "%s" compound type.', $method, $this));
    }

    /**
     * Sort intersections first, then classes, then builtins and order alphabetically within each group.
     *
     * @param array<T> $types
     * @return list<T>
     */
    private function sortSubtypesForRendering(Type ...$types): array
    {
        $prefix = function (Type $t): string {
            return match ($t::class) {
                    IntersectionType::class => '!!',
                    ObjectType::class => '!',
                    default => '',
                }.$t;
        };
        usort($types, fn (Type $a, Type $b): int => $prefix($a) <=> $prefix($b));

        return array_values($types);
    }
}
