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

class DummyWithStaticMutator
{
    public int $value = 0;

    private int $quantity = 0;

    public static function value(int $value): self
    {
        $dummy = new self();
        $dummy->value = $value;

        return $dummy;
    }

    public static function quantity(int $quantity): self
    {
        return self::value($quantity);
    }

    public static function amount(int $amount): self
    {
        return self::value($amount);
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }
}
