<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Cache\Tests\Adapter;

class RedisArrayAdapterTest extends AbstractRedisAdapterTest
{
    public static function setupBeforeClass()
    {
        parent::setupBeforeClass();
        if (!class_exists('RedisArray')) {
            self::markTestSkipped('The RedisArray class is required.');
        }
        self::$redis = new \RedisArray(array(getenv('REDIS_HOST')), array('lazy_connect' => true));
    }
}
