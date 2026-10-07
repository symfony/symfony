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

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

final class LocatorConsumer
{
    public function __construct(
        #[AutowireLocator('app.loader', indexAttribute: 'key')]
        private ContainerInterface $loaders,
    ) {
    }

    public function getLoaders(): ContainerInterface
    {
        return $this->loaders;
    }
}
