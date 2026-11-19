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

use Symfony\Component\TypeInfo\Type\CompositeTypeInterface;
use Symfony\Component\TypeInfo\Type\RefinementTypeInterface;
use Symfony\Component\TypeInfo\Type\WrappingTypeInterface;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 *
 * @template-covariant T
 */
abstract class Type implements \Stringable
{
    use TypeFactoryTrait;

    /**
     * Tells if the type is satisfied by the $specification callable.
     *
     * @param-immediately-invoked-callable $specification
     *
     * @param callable(self): bool $specification
     */
    public function isSatisfiedBy(callable $specification): bool
    {
        if ($this instanceof WrappingTypeInterface && $this->wrappedTypeIsSatisfiedBy($specification)) {
            return true;
        }

        if ($this instanceof CompositeTypeInterface && $this->composedTypesAreSatisfiedBy($specification)) {
            return true;
        }

        return $specification($this);
    }

    /**
     * Tells if the type (or one of its wrapped/composed parts) is identified by one of the $identifiers.
     */
    public function isIdentifiedBy(TypeIdentifier|string ...$identifiers): bool
    {
        $specification = static fn (Type $type): bool => $type->isIdentifiedBy(...$identifiers);

        if ($this instanceof WrappingTypeInterface && $this->wrappedTypeIsSatisfiedBy($specification)) {
            return true;
        }

        if ($this instanceof CompositeTypeInterface && $this->composedTypesAreSatisfiedBy($specification)) {
            return true;
        }

        return false;
    }

    public function isNullable(): bool
    {
        return false;
    }

    /**
     * Tells if the type (or one of its wrapped/composed parts) accepts the given $value.
     *
     * @psalm-assert-if-true =T $value
     */
    public function accepts(mixed $value): bool
    {
        // one level at a time, so that a wrapped type with its own check, such as a nullable type, a collection or an integer range, keeps it
        if ($this instanceof WrappingTypeInterface) {
            return $this->getWrappedType()->accepts($value);
        }

        if ($this instanceof CompositeTypeInterface) {
            return $this->composedTypesAreSatisfiedBy(static fn (Type $type): bool => $type->accepts($value));
        }

        return false;
    }

    /**
     * Tells where and why the $value is not accepted by the type.
     *
     * @return list<TypeMismatch>
     */
    public function getMismatches(mixed $value): array
    {
        return $this->accepts($value) ? [] : [new TypeMismatch('', $this, get_debug_type($value))];
    }

    /**
     * Maps the type (and its wrapped/composed parts) bottom-up with a $mapper.
     *
     * @param-immediately-invoked-callable $mapper
     *
     * @param callable(self): self $mapper
     */
    public function map(callable $mapper): self
    {
        return $mapper($this);
    }

    /**
     * Replaces the refinements in the type and in its parts, such as literals and integer ranges, with their builtin type.
     *
     * Parts that become identical are merged, so "1|2" becomes "int" and "?non-zero-int" becomes "?int".
     */
    public function withoutRefinements(): self
    {
        return $this->map(static fn (self $type): self => $type instanceof RefinementTypeInterface ? $type->getWrappedType() : $type);
    }

    /**
     * Traverses the type and its direct composed or wrapped parts.
     *
     * @return iterable<self>
     */
    public function traverse(bool $traverseComposite = true, bool $traverseWrapped = true): iterable
    {
        yield $this;

        if ($this instanceof CompositeTypeInterface && $traverseComposite) {
            foreach ($this->getTypes() as $type) {
                yield $type;
            }

            // prevent yielding twice when having a type that is both composite and wrapped
            return;
        }

        if ($this instanceof WrappingTypeInterface && $traverseWrapped) {
            yield $this->getWrappedType();
        }
    }
}
