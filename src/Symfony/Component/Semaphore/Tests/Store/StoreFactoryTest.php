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
use Symfony\Component\Semaphore\Store\RedisStore;
use Symfony\Component\Semaphore\Store\StoreFactory;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class StoreFactoryTest extends TestCase
{
    /**
     * @dataProvider validConnections
     */
    public function testCreateStore($connection, string $expectedStoreClass)
    {
        $store = StoreFactory::createStore($connection);

        $this->assertInstanceOf($expectedStoreClass, $store);
    }

    public static function validConnections(): \Generator
    {
        yield [new \Predis\Client(), RedisStore::class];

        if (class_exists(\Redis::class)) {
            yield [new \Redis(), RedisStore::class];
        }
        if (class_exists(\Redis::class) && class_exists(AbstractAdapter::class)) {
            yield ['redis://localhost', RedisStore::class];
            yield ['redis://localhost?lazy=1', RedisStore::class];
            yield ['redis://localhost?redis_cluster=1', RedisStore::class];
            yield ['redis://localhost?redis_cluster=1&lazy=1', RedisStore::class];
            yield ['redis:?host[localhost]&host[localhost:6379]&redis_cluster=1', RedisStore::class];
        }
    }
}
