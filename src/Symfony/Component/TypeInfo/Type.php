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
use Symfony\Component\TypeInfo\Type\WrappingTypeInterface;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 */
abstract class Type implements \Stringable
{
    use TypeFactoryTrait;

    /**
     * Tells if the type is satisfied by the $specification callable.
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
     */
    public function accepts(mixed $value): bool
    {
        $specification = static function (Type $type) use (&$specification, $value): bool {
            if ($type instanceof WrappingTypeInterface) {
                return $type->wrappedTypeIsSatisfiedBy($specification);
            }

            if ($type instanceof CompositeTypeInterface) {
                return $type->composedTypesAreSatisfiedBy($specification);
            }

            return $type->accepts($value);
        };

        return $this->isSatisfiedBy($specification);
    }

    /**
     * Traverses the whole type tree.
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
