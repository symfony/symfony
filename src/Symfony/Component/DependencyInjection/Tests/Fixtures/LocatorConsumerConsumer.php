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

final class LocatorConsumerConsumer
{
    public function __construct(
        private LocatorConsumer $locatorConsumer
    ) {
    }

    public function getLocatorConsumer(): LocatorConsumer
    {
        return $this->locatorConsumer;
    }
}
