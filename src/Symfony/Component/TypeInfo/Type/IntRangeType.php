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
 * Represents an integer range, such as "int<0, 10>" or "positive-int".
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @extends Type<int>
 *
 * @implements RefinementTypeInterface<BuiltinType<TypeIdentifier::INT>>
 */
final class IntRangeType extends Type implements RefinementTypeInterface
{
    private readonly BuiltinType $type;
    private readonly ?int $min;
    private readonly ?int $max;

    /**
     * @param int|null $min the lower bound, null or PHP_INT_MIN for none
     * @param int|null $max the upper bound, null or PHP_INT_MAX for none
     */
    public function __construct(?int $min, ?int $max)
    {
        // the integer limits are no bound at all
        $min = \PHP_INT_MIN === $min ? null : $min;
        $max = \PHP_INT_MAX === $max ? null : $max;

        if (null === $min && null === $max) {
            throw new InvalidArgumentException(\sprintf('Cannot create "%s" without bounds, use "int" instead.', self::class));
        }

        if (null !== $min && null !== $max && $min > $max) {
            throw new InvalidArgumentException(\sprintf('Cannot create "%s" with a minimum (%d) greater than its maximum (%d).', self::class, $min, $max));
        }

        $this->min = $min;
        $this->max = $max;
        $this->type = Type::int();
    }

    public function getMin(): ?int
    {
        return $this->min;
    }

    public function getMax(): ?int
    {
        return $this->max;
    }

    public function getWrappedType(): Type
    {
        return $this->type;
    }

    /**
     * @param-immediately-invoked-callable $specification
     */
    public function wrappedTypeIsSatisfiedBy(callable $specification): bool
    {
        return $this->getWrappedType()->isSatisfiedBy($specification);
    }

    public function accepts(mixed $value): bool
    {
        return \is_int($value) && (null === $this->min || $value >= $this->min) && (null === $this->max || $value <= $this->max);
    }

    public function __toString(): string
    {
        return \sprintf('int<%s, %s>', $this->min ?? 'min', $this->max ?? 'max');
    }
}
