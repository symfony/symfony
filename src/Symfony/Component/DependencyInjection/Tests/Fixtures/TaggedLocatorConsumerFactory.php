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

final class TaggedLocatorConsumerFactory
{
    public function __invoke(
        #[AutowireLocator('foo_bar', indexAttribute: 'key')]
        ContainerInterface $locator,
    ): TaggedLocatorConsumer {
        return new TaggedLocatorConsumer($locator);
    }
}
