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
     * @var \Redis
     */
    protected $validator;

    /**
     * @return \Redis|\RedisArray|\RedisCluster|\Predis\Client
     */
    abstract protected function createRedisClient(string $host);

    protected function setUp()
    {
        parent::setUp();

        if (!extension_loaded('redis')) {
            self::markTestSkipped('Extension redis required.');
        }

        $host = getenv('REDIS_HOST') ?: 'localhost';

        $this->validator = new \Redis();
        $this->validator->connect($host);

        $this->redisClient = $this->createRedisClient($host);
        $this->storage = new RedisSessionHandler(
            $this->redisClient,
            array('prefix' => self::PREFIX)
        );
    }

    protected function tearDown()
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
        $this->setFixture(self::PREFIX.'id1', null);
        $this->setFixture(self::PREFIX.'id2', 'abc123');

        $this->assertEquals('', $this->storage->read('id1'));
        $this->assertEquals('abc123', $this->storage->read('id2'));
    }

    public function testWriteSession()
    {
        $this->assertTrue($this->storage->write('id', 'data'));

        $this->assertTrue($this->hasFixture(self::PREFIX.'id'));
        $this->assertEquals('data', $this->getFixture(self::PREFIX.'id'));
    }

    public function testUseSessionGcMaxLifetimeAsTimeToLive()
    {
        $this->storage->write('id', 'data');
        $ttl = $this->fixtureTtl(self::PREFIX.'id');

        $this->assertLessThanOrEqual(ini_get('session.gc_maxlifetime'), $ttl);
        $this->assertGreaterThanOrEqual(0, $ttl);
    }

    public function testDestroySession()
    {
        $this->setFixture(self::PREFIX.'id', 'foo');

        $this->assertTrue($this->hasFixture(self::PREFIX.'id'));
        $this->assertTrue($this->storage->destroy('id'));
        $this->assertFalse($this->hasFixture(self::PREFIX.'id'));
    }

    public function testGcSession()
    {
        $this->assertTrue($this->storage->gc(123));
    }

    public function testUpdateTimestamp()
    {
        $lowTTL = 10;

        $this->setFixture(self::PREFIX.'id', 'foo', $lowTTL);
        $this->storage->updateTimestamp('id', array());

        $this->assertGreaterThan($lowTTL, $this->fixtureTtl(self::PREFIX.'id'));
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
        return array(
            array(array('prefix' => 'session'), true),
            array(array('prefix' => 'sfs', 'foo' => 'bar'), false),
        );
    }

    protected function setFixture($key, $value, $ttl = null)
    {
        if (null !== $ttl) {
            $this->validator->setex($key, $ttl, $value);
        } else {
            $this->validator->set($key, $value);
        }
    }

    protected function getFixture($key)
    {
        return $this->validator->get($key);
    }

    protected function hasFixture($key): bool
    {
        return $this->validator->exists($key);
    }

    protected function fixtureTtl($key): int
    {
        return $this->validator->ttl($key);
    }
}
