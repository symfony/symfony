<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Lock\Tests\Store;

use Symphony\Component\Lock\Store\RedisStore;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
abstract class AbstractRedisStoreTest extends AbstractStoreTest
{
    use ExpiringStoreTestTrait;

    /**
     * {@inheritdoc}
     */
    protected function getClockDelay()
    {
        return 250000;
    }

    /**
     * Return a RedisConnection.
     *
     * @return \Redis|\RedisArray|\RedisCluster|\Predis\Client
     */
    abstract protected function getRedisConnection();

    /**
     * {@inheritdoc}
     */
    public function getStore()
    {
        return new RedisStore($this->getRedisConnection());
    }
}
