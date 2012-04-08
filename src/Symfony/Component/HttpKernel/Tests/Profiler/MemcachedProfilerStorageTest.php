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

use Symfony\Component\HttpKernel\Profiler\MemcachedProfilerStorage;

class DummyMemcachedProfilerStorage extends MemcachedProfilerStorage
{
    public function getMemcached()
    {
        return parent::getMemcached();
    }
}

/**
 * @group memcached
 */
class MemcachedProfilerStorageTest extends AbstractProfilerStorageTest
{
    protected static $storage;

    public static function tearDownAfterClass()
    {
        if (self::$storage) {
            self::$storage->purge();
        }
    }

    protected function setUp()
    {
        if (!extension_loaded('memcached')) {
            $this->markTestSkipped('MemcachedProfilerStorageTest requires that the extension memcached is loaded');
        }

        self::$storage = new DummyMemcachedProfilerStorage('memcached://127.0.0.1:11211', '', '', 86400);
        try {
            self::$storage->getMemcached();
        } catch (\Exception $e) {
            $this->markTestSkipped('MemcachedProfilerStorageTest requires that there is a Memcache server present on localhost');
        }

        if (self::$storage) {
            self::$storage->purge();
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
