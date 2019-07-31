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

use Symfony\Bridge\PhpUnit\ForwardCompatTestTrait;

class PredisRedisClusterAdapterTest extends AbstractRedisAdapterTest
{
    use ForwardCompatTestTrait;

    private static function doSetUpBeforeClass()
    {
        if (!$hosts = getenv('REDIS_CLUSTER_HOSTS')) {
            self::markTestSkipped('REDIS_CLUSTER_HOSTS env var is not defined.');
        }
        self::$redis = new \Predis\Client(explode(' ', $hosts), ['cluster' => 'redis']);
    }

    private static function doTearDownAfterClass()
    {
        self::$redis = null;
    }
}
