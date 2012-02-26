<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpKernel\Profiler;

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

    public static function tearDownAfterClass()
    {
        if (self::$storage) {
            self::$storage->purge();
        }
    }

    protected function setUp()
    {
        if (extension_loaded('mongo')) {
            self::$storage = new DummyMongoDbProfilerStorage('mongodb://localhost/symfony_tests/profiler_data', '', '', 86400);
            try {
                self::$storage->getMongo();
                self::$storage->purge();
            } catch (\MongoConnectionException $e) {
                $this->markTestSkipped('MongoDbProfilerStorageTest requires that there is a MongoDB server present on localhost');
            }
        } else {
            $this->markTestSkipped('MongoDbProfilerStorageTest requires that the extension mongo is loaded');
        }
    }

    public function testCleanup()
    {
        $dt = new \DateTime('-2 day');
        for ($i = 0; $i < 3; $i++) {
            $dt->modify('-1 day');
            $profile = new Profile('time_' . $i);
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
}
