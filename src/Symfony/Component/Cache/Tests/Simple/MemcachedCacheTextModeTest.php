<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Simple;

use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Simple\MemcachedCache;

/**
 * @group legacy
 */
class MemcachedCacheTextModeTest extends MemcachedCacheTest
{
    public function createSimpleCache($defaultLifetime = 0)
    {
        $client = AbstractAdapter::createConnection('memcached://'.getenv('MEMCACHED_HOST'), ['binary_protocol' => false]);

        return new MemcachedCache($client, str_replace('\\', '.', __CLASS__), $defaultLifetime);
    }
}
