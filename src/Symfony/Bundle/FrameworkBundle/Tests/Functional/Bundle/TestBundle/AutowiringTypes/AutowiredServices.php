<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\AutowiringTypes;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class AutowiredServices
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly CacheItemPoolInterface $cachePool,
    ) {
    }

    public function getDispatcher()
    {
        return $this->dispatcher;
    }

    public function getCachePool()
    {
        return $this->cachePool;
    }
}
