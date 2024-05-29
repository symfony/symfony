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

final class TaggedConsumerWithExclude implements AutoconfiguredInterface2
{
    public function __construct(
        #[AutowireIterator(AutoconfiguredInterface2::class, exclude: self::class)]
        public iterable $items,
        #[AutowireLocator(AutoconfiguredInterface2::class, exclude: self::class)]
        public ContainerInterface $locator,
    ) {
    }
}
