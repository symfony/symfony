<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Simple;

/**
 * @group legacy
 */
class RedisArrayCacheTest extends AbstractRedisCacheTest
{
    public static function setupBeforeClass()
    {
        parent::setupBeforeClass();
        if (!class_exists('RedisArray')) {
            self::markTestSkipped('The RedisArray class is required.');
        }
        self::$redis = new \RedisArray([getenv('REDIS_HOST')], ['lazy_connect' => true]);
    }
}
