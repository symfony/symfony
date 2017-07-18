<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Tests\Store;

use Symfony\Component\Lock\Store\MemcachedStore;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 *
 * @requires extension memcached
 */
class MemcachedStoreTest extends AbstractStoreTest
{
    use ExpiringStoreTestTrait;

    public static function setupBeforeClass()
    {
        $memcached = new \Memcached();
        $memcached->addServer(getenv('MEMCACHED_HOST'), 11211);
        if (false === $memcached->getStats()) {
            self::markTestSkipped('Unable to connect to the memcache host');
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getClockDelay()
    {
        return 1000000;
    }

    /**
     * {@inheritdoc}
     */
    public function getStore()
    {
        $memcached = new \Memcached();
        $memcached->addServer(getenv('MEMCACHED_HOST'), 11211);

        return new MemcachedStore($memcached);
    }
}
