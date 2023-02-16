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

use PHPUnit\Framework\SkippedTestSuiteError;
use Symfony\Component\Lock\Exception\InvalidTtlException;
use Symfony\Component\Lock\Store\RedisStore;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 *
 * @requires extension redis
 *
 * @group integration
 */
class RedisStoreTest extends AbstractRedisStoreTestCase
{
    use SharedLockStoreTestTrait;

    public static function setUpBeforeClass(): void
    {
        try {
            (new \Redis())->connect(...explode(':', getenv('REDIS_HOST')));
        } catch (\Exception $e) {
            throw new SkippedTestSuiteError($e->getMessage());
        }
    }

    protected function getRedisConnection(): \Redis
    {
        $redis = new \Redis();
        $redis->connect(...explode(':', getenv('REDIS_HOST')));

        return $redis;
    }

    public function testInvalidTtl()
    {
        $this->expectException(InvalidTtlException::class);
        new RedisStore($this->getRedisConnection(), -1);
    }
}
