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
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Contracts\Service\Attribute\SubscribedService;

final class NestedAutowireLocatorConsumer
{
    public function __construct(
        #[AutowireLocator([
            'nested_locator' => new SubscribedService(type: ContainerInterface::class, attributes: new AutowireLocator('foo_bar', indexAttribute: 'key')),
            'nested_iterator' => new SubscribedService(type: 'iterable', attributes: new AutowireIterator('foo_bar')),
        ])]
        public readonly ContainerInterface $locator,
    ) {
    }
}
