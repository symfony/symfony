<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Layout;

/** @internal */
final class MarginStrut
{
    public readonly float $positive;
    public readonly float $negative;

    public function __construct(float ...$margins)
    {
        $this->positive = max(0.0, 0.0, ...$margins);
        $this->negative = min(0.0, 0.0, ...$margins);
    }

    public static function from(float $margin): self
    {
        static $zero = new self();

        return 0.0 === $margin ? $zero : new self($margin);
    }

    public function merge(self $other): self
    {
        if ($other->positive <= $this->positive && $other->negative >= $this->negative) {
            return $this;
        }
        if ($this->positive <= $other->positive && $this->negative >= $other->negative) {
            return $other;
        }

        return new self($this->positive, $this->negative, $other->positive, $other->negative);
    }

    public function value(): float
    {
        return $this->positive + $this->negative;
    }
}
