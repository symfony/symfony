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

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Semaphore\Key;
use Symfony\Component\Semaphore\Store\RedisStore;
use Symfony\Component\Semaphore\Store\StoreFactory;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
#[RequiresPhpExtension('redis')]
class RedisStoreTest extends AbstractRedisStoreTestCase
{
    public static function setUpBeforeClass(): void
    {
        try {
            (new \Redis())->connect(...explode(':', getenv('REDIS_HOST')));
        } catch (\Exception $e) {
            self::markTestSkipped($e->getMessage());
        }
    }

    protected function getRedisConnection(): \Redis
    {
        $redis = new \Redis();
        $redis->connect(...explode(':', getenv('REDIS_HOST')));

        return $redis;
    }

    public function testCloseResetsALazyConnection()
    {
        if (!class_exists(AbstractAdapter::class)) {
            $this->markTestSkipped('The Cache component is required.');
        }

        $store = StoreFactory::createStore('redis://'.getenv('REDIS_HOST'));
        $redis = (new \ReflectionProperty($store, 'redis'))->getValue($store);
        $key = new Key(uniqid(__METHOD__, true), 1);
        $store->save($key, 10);
        $this->assertTrue($redis->isLazyObjectInitialized());

        $store->close();
        $this->assertFalse($redis->isLazyObjectInitialized());

        $this->assertTrue($store->exists($key));
        $store->delete($key);
    }

    public function testCloseKeepsAnEagerConnection()
    {
        $redis = $this->getRedisConnection();
        $store = new RedisStore($redis);

        $store->close();

        $this->assertTrue($redis->isConnected());
    }
}
