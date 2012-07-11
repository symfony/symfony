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

use Symfony\Component\HttpKernel\Profiler\MongoDbProfilerStorage;
use Symfony\Component\HttpKernel\Profiler\Profile;

class DummyMongoDbProfilerStorage extends MongoDbProfilerStorage
{
    public function getMongo()
    {
        return parent::getMongo();
    }
}

class MongoDbProfilerStorageTest extends AbstractProfilerStorageTest
{
    protected static $storage;

    public static function setUpBeforeClass()
    {
        if (extension_loaded('mongo')) {
            self::$storage = new DummyMongoDbProfilerStorage('mongodb://localhost/symfony_tests/profiler_data', '', '', 86400);
            try {
                self::$storage->getMongo();
            } catch (\MongoConnectionException $e) {
                self::$storage = null;
            }
        }
    }

    public static function tearDownAfterClass()
    {
        if (self::$storage) {
            self::$storage->purge();
            self::$storage = null;
        }
    }

    public function testCleanup()
    {
        $dt = new \DateTime('-2 day');
        for ($i = 0; $i < 3; $i++) {
            $dt->modify('-1 day');
            $profile = new Profile('time_'.$i);
            $profile->setTime($dt->getTimestamp());
            $profile->setMethod('GET');
            self::$storage->write($profile);
        }
        $records = self::$storage->find('', '', 3, 'GET');
        $this->assertCount(1, $records, '->find() returns only one record');
        $this->assertEquals($records[0]['token'], 'time_2', '->find() returns the latest added record');
        self::$storage->purge();
    }

    /**
     * @return \Symfony\Component\HttpKernel\Profiler\ProfilerStorageInterface
     */
    protected function getStorage()
    {
        return self::$storage;
    }

    protected function setUp()
    {
        if (self::$storage) {
            self::$storage->purge();
        } else {
            $this->markTestSkipped('MongoDbProfilerStorageTest requires the mongo PHP extension and a MongoDB server on localhost');
        }
    }
}
