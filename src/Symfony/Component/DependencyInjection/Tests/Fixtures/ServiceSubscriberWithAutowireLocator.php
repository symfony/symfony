<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Contracts\Service\Attribute\SubscribedService;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

final class ServiceSubscriberWithAutowireLocator implements ServiceSubscriberInterface
{
    public function __construct(
        public readonly ContainerInterface $container,
    ) {
    }

    public static function getSubscribedServices(): array
    {
        return [
            new SubscribedService('nested_locator', ContainerInterface::class, attributes: new AutowireLocator('foo_bar', indexAttribute: 'key')),
        ];
    }
}
