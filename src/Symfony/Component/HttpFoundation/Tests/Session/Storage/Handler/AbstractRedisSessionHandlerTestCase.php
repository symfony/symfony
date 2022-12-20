<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Session\Storage\Handler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\RedisSessionHandler;

/**
 * @requires extension redis
 * @group time-sensitive
 */
abstract class AbstractRedisSessionHandlerTestCase extends TestCase
{
    protected const PREFIX = 'prefix_';

    /**
     * @var RedisSessionHandler
     */
    protected $storage;

    /**
     * @var \Redis|\RedisArray|\RedisCluster|\Predis\Client
     */
    protected $redisClient;

    /**
     * @return \Redis|\RedisArray|\RedisCluster|\Predis\Client
     */
    abstract protected function createRedisClient(string $host): object;

    protected function setUp(): void
    {
        self::setUp();

        if (!\extension_loaded('redis')) {
            self::markTestSkipped('Extension redis required.');
        }
        try {
            (new \Redis())->connect(...explode(':', getenv('REDIS_HOST')));
        } catch (\Exception $e) {
            self::markTestSkipped($e->getMessage());
        }

        $host = getenv('REDIS_HOST') ?: 'localhost';

        $this->redisClient = $this->createRedisClient($host);
        $this->storage = new RedisSessionHandler(
            $this->redisClient,
            ['prefix' => self::PREFIX]
        );
    }

    protected function tearDown(): void
    {
        $this->redisClient = null;
        $this->storage = null;

        self::tearDown();
    }

    public function testOpenSession()
    {
        self::assertTrue($this->storage->open('', ''));
    }

    public function testCloseSession()
    {
        self::assertTrue($this->storage->close());
    }

    public function testReadSession()
    {
        $this->redisClient->set(self::PREFIX.'id1', null);
        $this->redisClient->set(self::PREFIX.'id2', 'abc123');

        self::assertEquals('', $this->storage->read('id1'));
        self::assertEquals('abc123', $this->storage->read('id2'));
    }

    public function testWriteSession()
    {
        self::assertTrue($this->storage->write('id', 'data'));

        self::assertTrue((bool) $this->redisClient->exists(self::PREFIX.'id'));
        self::assertEquals('data', $this->redisClient->get(self::PREFIX.'id'));
    }

    public function testUseSessionGcMaxLifetimeAsTimeToLive()
    {
        $this->storage->write('id', 'data');
        $ttl = $this->redisClient->ttl(self::PREFIX.'id');

        self::assertLessThanOrEqual(\ini_get('session.gc_maxlifetime'), $ttl);
        self::assertGreaterThanOrEqual(0, $ttl);
    }

    public function testDestroySession()
    {
        $this->redisClient->set(self::PREFIX.'id', 'foo');

        self::assertTrue((bool) $this->redisClient->exists(self::PREFIX.'id'));
        self::assertTrue($this->storage->destroy('id'));
        self::assertFalse((bool) $this->redisClient->exists(self::PREFIX.'id'));
    }

    public function testGcSession()
    {
        self::assertIsInt($this->storage->gc(123));
    }

    public function testUpdateTimestamp()
    {
        $lowTtl = 10;

        $this->redisClient->setex(self::PREFIX.'id', $lowTtl, 'foo');
        $this->storage->updateTimestamp('id', 'data');

        self::assertGreaterThan($lowTtl, $this->redisClient->ttl(self::PREFIX.'id'));
    }

    /**
     * @dataProvider getOptionFixtures
     */
    public function testSupportedParam(array $options, bool $supported)
    {
        try {
            new RedisSessionHandler($this->redisClient, $options);
            self::assertTrue($supported);
        } catch (\InvalidArgumentException $e) {
            self::assertFalse($supported);
        }
    }

    public function getOptionFixtures(): array
    {
        return [
            [['prefix' => 'session'], true],
            [['ttl' => 1000], true],
            [['prefix' => 'sfs', 'ttl' => 1000], true],
            [['prefix' => 'sfs', 'foo' => 'bar'], false],
            [['ttl' => 'sfs', 'foo' => 'bar'], false],
        ];
    }

    /**
     * @dataProvider getTtlFixtures
     */
    public function testUseTtlOption(int $ttl)
    {
        $options = [
            'prefix' => self::PREFIX,
            'ttl' => $ttl,
        ];

        $handler = new RedisSessionHandler($this->redisClient, $options);
        $handler->write('id', 'data');
        $redisTtl = $this->redisClient->ttl(self::PREFIX.'id');

        self::assertLessThan($redisTtl, $ttl - 5);
        self::assertGreaterThan($redisTtl, $ttl + 5);
    }

    public function getTtlFixtures(): array
    {
        return [
            ['ttl' => 5000],
            ['ttl' => 120],
            ['ttl' => 60],
        ];
    }
}
