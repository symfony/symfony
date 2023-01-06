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
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;

final class LocatorConsumerFactory
{
    public function __invoke(
        #[TaggedLocator('foo_bar', indexAttribute: 'key')]
        ContainerInterface $locator
    ): LocatorConsumer {
        return new LocatorConsumer($locator);
    }
}
