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

class DummyWithStaticConstructorAndAccessor
{
    public bool $positive = false;

    public function __construct(private readonly int $quantity = 1)
    {
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public static function one(): self
    {
        return new self(1);
    }

    public static function positive(): self
    {
        return new self(2);
    }

    public function isZero(): bool
    {
        return 0 === $this->quantity;
    }
}
