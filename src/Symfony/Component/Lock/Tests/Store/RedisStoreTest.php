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

use Symfony\Component\Lock\Store\RedisStore;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 *
 * @requires extension redis
 */
class RedisStoreTest extends AbstractRedisStoreTest
{
    public static function setUpBeforeClass(): void
    {
        if (!@((new \Redis())->connect(getenv('REDIS_HOST')))) {
            $e = error_get_last();
            self::markTestSkipped($e['message']);
        }
    }

    /**
     * @return \Redis
     */
    protected function getRedisConnection(): object
    {
        $redis = new \Redis();
        $redis->connect(getenv('REDIS_HOST'));

        return $redis;
    }

    public function testInvalidTtl()
    {
        $this->expectException('Symfony\Component\Lock\Exception\InvalidTtlException');
        new RedisStore($this->getRedisConnection(), -1);
    }
}
