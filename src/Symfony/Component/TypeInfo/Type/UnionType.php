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
final class UnionType extends Type implements CompositeTypeInterface, NullableTypeInterface
{
    /**
     * @use CompositeTypeTrait<T>
     */
    use CompositeTypeTrait;

    public function __construct(Type ...$types)
    {
        if (\count($types) < 2) {
            throw new InvalidArgumentException(\sprintf('"%s" expects at least 2 types.', self::class));
        }

        foreach ($types as $t) {
            if ($t instanceof self || \in_array($t->getTypeIdentifier(), [TypeIdentifier::NEVER, TypeIdentifier::VOID], true)) {
                throw new InvalidArgumentException(\sprintf('Cannot set "%s" as a "%s" part.', $t, self::class));
            }
        }
        // Sort intersections first, then classes, then builtins
        $prefix = function (Type $t): string {
            return match ($t::class) {
                IntersectionType::class => '!!',
                ObjectType::class => '!',
                default => '',
            }.$t;
        };
        usort($types, fn (Type $a, Type $b): int => $prefix($a) <=> $prefix($b));

        $this->types = array_values(array_unique($types));
    }

    public function getTypeIdentifier(): TypeIdentifier
    {
        $identifiers = array_values(array_unique(array_map(fn($t) => $t->getTypeIdentifier(), $this->getTypes())));

        return 1 === count($identifiers) ? $identifiers[0] : TypeIdentifier::MIXED;
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

    public function isNullable(): bool
    {
        foreach ($this->getTypes() as $type) {
            if ($type instanceof NullableTypeInterface && $type->isNullable()) {
                return true;
            }
        }

        return false;
    }

    public function asNonNullable(): Type
    {
        if (!$this->isNullable()) {
            return $this;
        }
        $nonNullableTypes = [];
        foreach ($this->getTypes() as $type) {
            if (TypeIdentifier::NULL === $type->getTypeIdentifier()) {
                continue;
            }
            if ($type instanceof NullableTypeInterface && $type->isNullable()) {
                $type = $type->asNonNullable();
            }
            $nonNullableTypes[] = $type instanceof self ? $type->getTypes() : [$type];
        }
        $nonNullableTypes = array_merge(...$nonNullableTypes);

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
}
