<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Adapter;

use Symfony\Component\Cache\Traits\MemcachedTrait;

class MemcachedAdapter extends AbstractAdapter
{
    use MemcachedTrait;

    protected $maxIdLength = 250;

    /**
     * Using a MemcachedAdapter with a TagAwareAdapter for storing tags is discouraged.
     * Using a RedisAdapter is recommended instead. If you cannot do otherwise, be aware that:
     * - the Memcached::OPT_BINARY_PROTOCOL must be enabled
     *   (that's the default when using MemcachedAdapter::createConnection());
     * - tags eviction by Memcached's LRU algorithm will break by-tags invalidation;
     *   your Memcached memory should be large enough to never trigger LRU.
     *
     * Using a MemcachedAdapter as a pure items store is fine.
     */
    public function __construct(\Memcached $client, $namespace = '', $defaultLifetime = 0)
    {
        $this->init($client, $namespace, $defaultLifetime);
    }
}
