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

use Symfony\Component\HttpKernel\Profiler\MemcacheProfilerStorage;

class DummyMemcacheProfilerStorage extends MemcacheProfilerStorage
{
    public function getMemcache()
    {
        return parent::getMemcache();
    }
}

/**
 * @group memcached
 */
class MemcacheProfilerStorageTest extends AbstractProfilerStorageTest
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
        if (!extension_loaded('memcache')) {
            $this->markTestSkipped('MemcacheProfilerStorageTest requires that the extension memcache is loaded');
        }

        self::$storage = new DummyMemcacheProfilerStorage('memcache://127.0.0.1:11211', '', '', 86400);
        try {
            self::$storage->getMemcache();
            $stats = self::$storage->getMemcache()->getExtendedStats();
            if (!isset($stats['127.0.0.1:11211']) || $stats['127.0.0.1:11211'] === false) {
                throw new \Exception();
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('MemcacheProfilerStorageTest requires that there is a Memcache server present on localhost');
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
