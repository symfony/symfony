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

use Symfony\Component\Cache\Adapter\TagAwareRedisAdapter;

class TagAwareRedisAdapterTest extends AbstractRedisAdapterTest
{
    use TagAwareAdapterTestTrait;

    public static function setupBeforeClass()
    {
        parent::setupBeforeClass();
        self::$redis = new \Redis();
        self::$redis->connect('127.0.0.1');
    }

    public function createCachePool($defaultLifeTime = 0)
    {
        return new TagAwareRedisAdapter(self::$redis, str_replace('\\', '.', __CLASS__), $defaultLifeTime);
    }
}
