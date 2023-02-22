<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Semaphore\Tests\Store;

use PHPUnit\Framework\SkippedTestSuiteError;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 *
 * @requires extension redis
 */
class RedisArrayStoreTest extends AbstractRedisStoreTestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!class_exists(\RedisArray::class)) {
            throw new SkippedTestSuiteError('The RedisArray class is required.');
        }
        try {
            (new \Redis())->connect(...explode(':', getenv('REDIS_HOST')));
        } catch (\Exception $e) {
            throw new SkippedTestSuiteError($e->getMessage());
        }
    }

    protected function getRedisConnection(): \RedisArray
    {
        return new \RedisArray([getenv('REDIS_HOST')]);
    }
}
