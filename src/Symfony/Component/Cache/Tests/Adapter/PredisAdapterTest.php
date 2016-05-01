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

class PredisAdapterTest extends AbstractRedisAdapterTest
{
    public static function setupBeforeClass()
    {
        parent::setupBeforeClass();
        self::$redis = new \Predis\Client();
    }

    public function testCreateConnection()
    {
        $redis = RedisAdapter::createConnection('redis://localhost/1', array('class' => \Predis\Client::class, 'timeout' => 3));
        $this->assertInstanceOf(\Predis\Client::class, $redis);

        $connection = $redis->getConnection();
        $this->assertInstanceOf(StreamConnection::class, $connection);

        $params = array(
            'scheme' => 'tcp',
            'host' => 'localhost',
            'path' => '',
            'dbindex' => '1',
            'port' => 6379,
            'class' => 'Predis\Client',
            'timeout' => 3,
            'persistent' => 0,
            'read_timeout' => 0,
            'retry_interval' => 0,
            'database' => '1',
            'password' => null,
        );
        $this->assertSame($params, $connection->getParameters()->toArray());
    }
}
