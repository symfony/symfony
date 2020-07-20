<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Tests\Store;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 *
 * @requires extension redis
 * @group integration
 */
class RedisArrayStoreTest extends AbstractRedisStoreTest
{
    public static function setUpBeforeClass(): void
    {
        if (!class_exists('RedisArray')) {
            self::markTestSkipped('The RedisArray class is required.');
        }
        try {
            (new \Redis())->connect(getenv('REDIS_HOST'));
        } catch (\Exception $e) {
            self::markTestSkipped($e->getMessage());
        }
    }

    /**
     * @return \RedisArray
     */
    protected function getRedisConnection(): object
    {
        $redis = new \RedisArray([getenv('REDIS_HOST')]);

        return $redis;
    }
}
