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
 * An integer between two bounds, such as "int<1, max>" or "positive-int".
 *
 * @author Martin Rademacher <mano@radebatz.net>
 *
 * @extends ExplicitType<TypeIdentifier::INT>
 */
final class IntRangeType extends ExplicitType
{
    public function __construct(
        private readonly int $from = \PHP_INT_MIN,
        private readonly int $to = \PHP_INT_MAX,
        ?string $explicitType = null,
    ) {
        parent::__construct(TypeIdentifier::INT, $explicitType ?? self::canonical($from, $to));
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
        return \is_int($value) && $this->from <= $value && $value <= $this->to;
    }

    public function __toString(): string
    {
        return self::canonical($this->from, $this->to);
    }

    private static function canonical(int $from, int $to): string
    {
        return \sprintf('int<%s, %s>', \PHP_INT_MIN === $from ? 'min' : $from, \PHP_INT_MAX === $to ? 'max' : $to);
    }
}
