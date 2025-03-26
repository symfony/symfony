<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\Tests\Fixtures;

/**
 * @template T of int|string
 * @template U
 */
final class DummyWithTemplates
{
    private int $price;

    /**
     * @template T of int|float
     * @template V
     *
     * @return T
     */
    public function getPrice(bool $inCents = false): int|float
    {
        return $inCents ? $this->price : $this->price / 100;
    }
}
