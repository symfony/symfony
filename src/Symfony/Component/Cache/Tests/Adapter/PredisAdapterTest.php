<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Adapter;

use Predis\Connection\StreamConnection;
use Symfony\Component\Cache\Adapter\RedisAdapter;

/**
 * @group integration
 */
class PredisAdapterTest extends AbstractRedisAdapterTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$redis = new \Predis\Client(array_combine(['host', 'port'], explode(':', getenv('REDIS_HOST')) + [1 => 6379]), ['prefix' => 'prefix_']);
    }

    public function testCreateConnection()
    {
        $redisHost = getenv('REDIS_HOST');

        $redis = RedisAdapter::createConnection('redis://'.$redisHost.'/1', ['class' => \Predis\Client::class, 'timeout' => 3]);
        $this->assertInstanceOf(\Predis\Client::class, $redis);

        $connection = $redis->getConnection();
        $this->assertInstanceOf(StreamConnection::class, $connection);

        $redisHost = explode(':', $redisHost);
        $params = [
            'scheme' => 'tcp',
            'host' => $redisHost[0],
            'port' => (int) ($redisHost[1] ?? 6379),
            'persistent' => 0,
            'timeout' => 3,
            'read_write_timeout' => 0,
            'tcp_nodelay' => true,
            'database' => '1',
        ];
        $this->assertSame($params, $connection->getParameters()->toArray());
    }

    public function testCreateSslConnection()
    {
        $redisHost = getenv('REDIS_HOST');

        $redis = RedisAdapter::createConnection('rediss://'.$redisHost.'/1?ssl[verify_peer]=0', ['class' => \Predis\Client::class, 'timeout' => 3]);
        $this->assertInstanceOf(\Predis\Client::class, $redis);

        $connection = $redis->getConnection();
        $this->assertInstanceOf(StreamConnection::class, $connection);

        $redisHost = explode(':', $redisHost);
        $params = [
            'scheme' => 'tls',
            'host' => $redisHost[0],
            'port' => (int) ($redisHost[1] ?? 6379),
            'ssl' => ['verify_peer' => '0'],
            'persistent' => 0,
            'timeout' => 3,
            'read_write_timeout' => 0,
            'tcp_nodelay' => true,
            'database' => '1',
        ];
        $this->assertSame($params, $connection->getParameters()->toArray());
    }

    public function testAclUserPasswordAuth()
    {
        // creating user via php-redis cause Predis (v1.1.10) does not support ACL command yet
        $redis = RedisAdapter::createConnection('redis://'.getenv('REDIS_HOST'));

        if (version_compare($redis->info()['redis_version'], '6.0', '<')) {
            $this->markTestSkipped('Redis server >= 6.0 required');
        }

        $this->assertTrue($redis->acl('SETUSER', 'predis', 'on'));
        $this->assertTrue($redis->acl('SETUSER', 'predis', '>password'));
        $this->assertTrue($redis->acl('SETUSER', 'predis', 'allkeys'));
        $this->assertTrue($redis->acl('SETUSER', 'predis', '+@all'));

        $predis = RedisAdapter::createConnection('redis://predis:password@'.getenv('REDIS_HOST'), ['class' => \Predis\Client::class]);
        $this->assertInstanceOf(\Predis\Client::class, $predis);
        $this->assertSame('OK', $predis->set(__FUNCTION__, 'value2')->getPayload());

        $this->assertSame(1, $redis->acl('DELUSER', 'predis'));
    }
}
