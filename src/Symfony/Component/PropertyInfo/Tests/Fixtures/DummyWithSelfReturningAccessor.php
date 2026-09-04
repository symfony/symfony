<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

interface AmountInterface
{
}

class DummyWithSelfReturningAccessor implements AmountInterface
{
    public bool $rounded = false;

    public function __construct(private readonly int $value = 1)
    {
    }

    public function isNegative(): bool
    {
        return 0 > $this->value;
    }

    public function negative(): self
    {
        return new self(-$this->value);
    }

    public function isPositive(): bool
    {
        return 0 < $this->value;
    }

    public function positive(): static
    {
        return $this->isPositive() ? $this : new static(-$this->value);
    }

    public function isHalved(): bool
    {
        return 0 === $this->value % 2;
    }

    public function halved(): AmountInterface
    {
        return new self(intdiv($this->value, 2));
    }

    public function isIncremented(): bool
    {
        return 0 !== $this->value;
    }

    public function incremented(): self|int
    {
        return new self($this->value + 1);
    }

    public function isTruncated(): bool
    {
        return false;
    }

    public function truncated(): \ArrayObject
    {
        return new \ArrayObject([$this->value]);
    }

    public function rounded(): self
    {
        return $this;
    }

    public function doubled(): self
    {
        return new self($this->value * 2);
    }
}
