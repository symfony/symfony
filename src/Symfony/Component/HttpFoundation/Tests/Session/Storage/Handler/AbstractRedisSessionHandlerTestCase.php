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
    abstract protected function createRedisClient(string $host);

    protected function setUp(): void
    {
        parent::setUp();

        if (!\extension_loaded('redis')) {
            self::markTestSkipped('Extension redis required.');
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

        parent::tearDown();
    }

    public function testOpenSession()
    {
        $this->assertTrue($this->storage->open('', ''));
    }

    public function testCloseSession()
    {
        $this->assertTrue($this->storage->close());
    }

    public function testReadSession()
    {
        $this->redisClient->set(self::PREFIX.'id1', null);
        $this->redisClient->set(self::PREFIX.'id2', 'abc123');

        $this->assertEquals('', $this->storage->read('id1'));
        $this->assertEquals('abc123', $this->storage->read('id2'));
    }

    public function testWriteSession()
    {
        $this->assertTrue($this->storage->write('id', 'data'));

        $this->assertTrue((bool) $this->redisClient->exists(self::PREFIX.'id'));
        $this->assertEquals('data', $this->redisClient->get(self::PREFIX.'id'));
    }

    public function testUseSessionGcMaxLifetimeAsTimeToLive()
    {
        $this->storage->write('id', 'data');
        $ttl = $this->redisClient->ttl(self::PREFIX.'id');

        $this->assertLessThanOrEqual(ini_get('session.gc_maxlifetime'), $ttl);
        $this->assertGreaterThanOrEqual(0, $ttl);
    }

    public function testDestroySession()
    {
        $this->redisClient->set(self::PREFIX.'id', 'foo');

        $this->assertTrue((bool) $this->redisClient->exists(self::PREFIX.'id'));
        $this->assertTrue($this->storage->destroy('id'));
        $this->assertFalse((bool) $this->redisClient->exists(self::PREFIX.'id'));
    }

    public function testGcSession()
    {
        $this->assertTrue($this->storage->gc(123));
    }

    public function testUpdateTimestamp()
    {
        $lowTtl = 10;

        $this->redisClient->setex(self::PREFIX.'id', $lowTtl, 'foo');
        $this->storage->updateTimestamp('id', []);

        $this->assertGreaterThan($lowTtl, $this->redisClient->ttl(self::PREFIX.'id'));
    }

    /**
     * @dataProvider getOptionFixtures
     */
    public function testSupportedParam(array $options, bool $supported)
    {
        try {
            new RedisSessionHandler($this->redisClient, $options);
            $this->assertTrue($supported);
        } catch (\InvalidArgumentException $e) {
            $this->assertFalse($supported);
        }
    }

    public function getOptionFixtures(): array
    {
        return [
            [['prefix' => 'session'], true],
            [['prefix' => 'sfs', 'foo' => 'bar'], false],
        ];
    }
}
