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
 * @psalm-template-covariant T of int|string
 * @psalm-template-covariant U
 */
final class DummyWithPsalmCovariantTemplates
{
    private int $price;

    /**
     * @psalm-template-covariant T of int|float
     * @psalm-template-covariant V
     *
     * @return T
     */
    public function getPrice(bool $inCents = false): int|float
    {
        return $inCents ? $this->price : $this->price / 100;
    }
}
