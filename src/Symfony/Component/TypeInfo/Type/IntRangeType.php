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

use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Int range type.
 *
 * @author Martin Rademacher <mano@radebatz.net>
 *
 * @extends BuiltinType<TypeIdentifier::INT>
 */
class IntRangeType extends BuiltinType
{
    public function __construct(
        private int $from = \PHP_INT_MIN,
        private int $to = \PHP_INT_MAX,
        private bool $zeroIncluded = true,
    ) {
        parent::__construct(TypeIdentifier::INT);
    }

    public function getFrom(): int
    {
        return $this->from;
    }

    public function getTo(): int
    {
        return $this->to;
    }

    public function isZeroIncluded(): bool
    {
        return $this->zeroIncluded;
    }

    public function accepts(mixed $value): bool
    {
        return \is_int($value)
            && $this->from <= $value && $value <= $this->to
            && (0 !== $value || $this->zeroIncluded);
    }

    public function __toString(): string
    {
        $min = \PHP_INT_MIN === $this->from ? 'min' : $this->from;
        $max = \PHP_INT_MAX === $this->to ? 'max' : $this->to;

        $template = 'int<%s, %s>';
        if (\is_string($min) && \is_string($max)) {
            return $this->zeroIncluded ? \sprintf($template, $min, $max) : 'non-zero-int';
        }

        if (\in_array($min, [0, 1], true) && 'max' === $max) {
            return 0 === $min ? 'non-negative-int' : 'positive-int';
        } elseif ('min' === $min && \in_array($max, [-1, 0])) {
            return 0 === $max ? 'non-positive-int' : 'negative-int';
        }

        return \sprintf($template, $min, $max);
    }
}
