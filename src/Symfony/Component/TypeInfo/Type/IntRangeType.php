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
final class IntRangeType extends BuiltinType
{
    public function __construct(
        private int $from = \PHP_INT_MIN,
        private int $to = \PHP_INT_MAX,
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

    public function accepts(mixed $value): bool
    {
        return parent::accepts($value) && $this->from <= $value && $value <= $this->to;
    }

    public function __toString(): string
    {
        $min = \PHP_INT_MIN === $this->from ? 'min' : $this->from;
        $max = \PHP_INT_MAX === $this->to ? 'max' : $this->to;

        return \sprintf('int<%s, %s>', $min, $max);
    }
}
