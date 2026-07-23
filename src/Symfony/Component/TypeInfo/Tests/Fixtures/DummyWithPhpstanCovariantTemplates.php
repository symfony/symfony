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
 * @phpstan-template-covariant T of int|string
 * @phpstan-template-covariant U
 */
final class DummyWithPhpstanCovariantTemplates
{
    private int $price;

    /**
     * @phpstan-template-covariant T of int|float
     * @phpstan-template-covariant V
     *
     * @return T
     */
    public function getPrice(bool $inCents = false): int|float
    {
        return $inCents ? $this->price : $this->price / 100;
    }
}
