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

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ClosureConsumer
{
    public function __construct(
        #[Autowire(service: 'app.loaders::loadAdults')]
        private \Closure $loadAdults,
    ) {
    }

    public function loadAdults(int $age): bool
    {
        return ($this->loadAdults)($age);
    }
}
