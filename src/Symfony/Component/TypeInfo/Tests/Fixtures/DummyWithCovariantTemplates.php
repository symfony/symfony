<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\TypeInfo\Tests\Fixtures;

/**
 * @template-covariant T of int|string
 * @template-covariant U
 */
final class DummyWithCovariantTemplates
{
    private int $price;

    /**
     * @template-covariant T of int|float
     * @template-covariant V
     *
     * @return T
     */
    public function getPrice(bool $inCents = false): int|float
    {
        return $inCents ? $this->price : $this->price / 100;
    }
}
