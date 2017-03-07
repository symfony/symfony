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

class PredisClusterAdapterTest extends AbstractRedisAdapterTest
{
    public static function setupBeforeClass()
    {
        parent::setupBeforeClass();
        self::$redis = new \Predis\Client(array(getenv('REDIS_HOST')));
    }

    public static function tearDownAfterClass()
    {
        self::$redis->getConnection()->getConnectionByKey('foo')->executeCommand(self::$redis->createCommand('FLUSHDB'));
        self::$redis = null;
    }
}
