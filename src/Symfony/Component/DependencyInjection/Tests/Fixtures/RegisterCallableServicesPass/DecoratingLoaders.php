<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass;

final class DecoratingLoaders
{
    public function __construct(
        private Loaders $inner,
    ) {
    }

    public function loadAdults(int $age): bool
    {
        return !$this->inner->loadAdults($age);
    }

    public function loadMinors(int $age): bool
    {
        return $this->inner->loadMinors($age);
    }
}
