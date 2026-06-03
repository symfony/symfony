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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Contracts\Service\Attribute\SubscribedService;

final class AutowireLocatorConsumer
{
    public function __construct(
        #[AutowireLocator([
            BarTagClass::class,
            'with_key' => FooTagClass::class,
            'nullable' => '?invalid',
            'subscribed' => new SubscribedService(type: 'string', attributes: new Autowire('%some.parameter%')),
            'subscribed1' => new Autowire('%some.parameter%'),
        ])]
        public readonly ContainerInterface $locator,
    ) {
    }
}
