<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Profiler;

use Symfony\Component\HttpKernel\Profiler\RedisProfilerStorage;

class DummyRedisProfilerStorage extends RedisProfilerStorage
{
    public function getRedis()
    {
        return parent::getRedis();
    }
}

class RedisProfilerStorageTest extends AbstractProfilerStorageTest
{
    protected static $storage;

    protected function setUp()
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('RedisProfilerStorageTest requires redis extension to be loaded');
        }

        self::$storage = new DummyRedisProfilerStorage('redis://127.0.0.1:6379', '', '', 86400);
        try {
            self::$storage->getRedis();

            self::$storage->purge();

        } catch (\Exception $e) {
            self::$storage = false;
            $this->markTestSkipped('RedisProfilerStorageTest requires that there is a Redis server present on localhost');
        }
    }

    protected function tearDown()
    {
        if (self::$storage) {
            self::$storage->purge();
            self::$storage->getRedis()->close();
            self::$storage = false;
        }
    }

    /**
     * @return \Symfony\Component\HttpKernel\Profiler\ProfilerStorageInterface
     */
    protected function getStorage()
    {
        return self::$storage;
    }
}
