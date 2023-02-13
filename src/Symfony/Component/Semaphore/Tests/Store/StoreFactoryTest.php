<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Semaphore\Tests\Store;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Traits\RedisProxy;
use Symfony\Component\Semaphore\Store\RedisStore;
use Symfony\Component\Semaphore\Store\StoreFactory;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 *
 * @requires extension redis
 */
class StoreFactoryTest extends TestCase
{
    public function testCreateRedisStore()
    {
        $store = StoreFactory::createStore($this->createMock(\Redis::class));

        $this->assertInstanceOf(RedisStore::class, $store);
    }

    public function testCreateRedisProxyStore()
    {
        if (!class_exists(RedisProxy::class)) {
            $this->markTestSkipped();
        }

        $store = StoreFactory::createStore($this->createMock(RedisProxy::class));

        $this->assertInstanceOf(RedisStore::class, $store);
    }

    public function testCreateRedisAsDsnStore()
    {
        if (!class_exists(RedisProxy::class)) {
            $this->markTestSkipped();
        }

        $store = StoreFactory::createStore('redis://localhost');

        $this->assertInstanceOf(RedisStore::class, $store);
    }

    public function testCreatePredisStore()
    {
        if (!class_exists(\Predis\Client::class)) {
            $this->markTestSkipped();
        }

        $store = StoreFactory::createStore(new \Predis\Client());

        $this->assertInstanceOf(RedisStore::class, $store);
    }
}
