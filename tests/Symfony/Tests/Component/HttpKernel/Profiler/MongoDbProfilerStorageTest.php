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

class DummyMongoDbProfilerStorage extends MongoDbProfilerStorage {
    public function getMongo() {
        return parent::getMongo();
    }
}

class MongoDbProfilerStorageTest extends \PHPUnit_Framework_TestCase
{
    protected static $storage;

    protected function setUp()
    {
        if (extension_loaded('mongo')) {
            self::$storage = new DummyMongoDbProfilerStorage('mongodb://localhost/symfony_tests/profiler_data', '', '', 86400);
            try {
                self::$storage->getMongo();
            } catch(\MongoConnectionException $e) {
                $this->markTestSkipped('MongoDbProfilerStorageTest requires that there is a MongoDB server present on localhost');
            }
            self::$storage->purge();
        } else {
            $this->markTestSkipped('MongoDbProfilerStorageTest requires that the extension mongo is loaded');
        }
    }

    public function testStore()
    {
        for ($i = 0; $i < 10; $i++) {
            $profile = new Profile('token_'.$i);
            $profile->setIp('127.0.0.1');
            $profile->setUrl('http://foo.bar');
            $profile->setMethod('GET');
            self::$storage->write($profile);
        }
        $this->assertCount(10, self::$storage->find('127.0.0.1', 'http://foo.bar', 20, 'GET'), '->write() stores data in the database');
        self::$storage->purge();
    }

    public function testStoreSpecialCharsInUrl()
    {
        // The storage accepts special characters in URLs (Even though URLs are not
        // supposed to contain them)
        $profile = new Profile('simple_quote');
        $profile->setUrl('127.0.0.1', 'http://foo.bar/\'');
        $profile->setMethod('GET');
        self::$storage->write($profile);
        $this->assertTrue(false !== self::$storage->read('simple_quote'), '->write() accepts single quotes in URL');

        $profile = new Profile('double_quote');
        $profile->setUrl('127.0.0.1', 'http://foo.bar/"');
        $profile->setMethod('GET');
        self::$storage->write($profile);
        $this->assertTrue(false !== self::$storage->read('double_quote'), '->write() accepts double quotes in URL');

        $profile = new Profile('backslash');
        $profile->setUrl('127.0.0.1', 'http://foo.bar/\\');
        $profile->setMethod('GET');
        self::$storage->write($profile);
        $this->assertTrue(false !== self::$storage->read('backslash'), '->write() accpets backslash in URL');

        self::$storage->purge();
    }

    public function testStoreTime()
    {
        $dt = new \DateTime('now');
        for ($i = 0; $i < 3; $i++) {
            $dt->modify('+1 minute');
            $profile = new Profile('time_'.$i);
            $profile->setIp('127.0.0.1');
            $profile->setUrl('http://foo.bar');
            $profile->setTime($dt->getTimestamp());
            $profile->setMethod('GET');
            self::$storage->write($profile);
        }
        $records = self::$storage->find('', '', 3, 'GET');
        $this->assertCount(3, $records, '->find() returns all previously added records');
        $this->assertEquals($records[0]['token'], 'time_2', '->find() returns records ordered by time in descendant order');
        $this->assertEquals($records[1]['token'], 'time_1', '->find() returns records ordered by time in descendant order');
        $this->assertEquals($records[2]['token'], 'time_0', '->find() returns records ordered by time in descendant order');
        self::$storage->purge();
    }

    public function testRetrieveByIp()
    {
        $profile = new Profile('token');
        $profile->setIp('127.0.0.1');
        $profile->setMethod('GET');

        self::$storage->write($profile);

        $this->assertCount(1, self::$storage->find('127.0.0.1', '', 10, 'GET'), '->find() retrieve a record by IP');
        $this->assertCount(0, self::$storage->find('127.0.%.1', '', 10, 'GET'), '->find() does not interpret a "%" as a wildcard in the IP');
        $this->assertCount(0, self::$storage->find('127.0._.1', '', 10, 'GET'), '->find() does not interpret a "_" as a wildcard in the IP');

        self::$storage->purge();
    }

    public function testRetrieveByUrl()
    {
        $profile = new Profile('simple_quote');
        $profile->setIp('127.0.0.1');
        $profile->setUrl('http://foo.bar/\'');
        $profile->setMethod('GET');
        self::$storage->write($profile);

        $profile = new Profile('double_quote');
        $profile->setIp('127.0.0.1');
        $profile->setUrl('http://foo.bar/"');
        $profile->setMethod('GET');
        self::$storage->write($profile);

        $profile = new Profile('backslash');
        $profile->setIp('127.0.0.1');
        $profile->setUrl('http://foo\\bar/');
        $profile->setMethod('GET');
        self::$storage->write($profile);

        $profile = new Profile('percent');
        $profile->setIp('127.0.0.1');
        $profile->setUrl('http://foo.bar/%');
        $profile->setMethod('GET');
        self::$storage->write($profile);

        $profile = new Profile('underscore');
        $profile->setIp('127.0.0.1');
        $profile->setUrl('http://foo.bar/_');
        $profile->setMethod('GET');
        self::$storage->write($profile);

        $this->assertCount(1, self::$storage->find('127.0.0.1', 'http://foo.bar/\'', 10, 'GET'), '->find() accepts single quotes in URLs');
        $this->assertCount(1, self::$storage->find('127.0.0.1', 'http://foo.bar/"', 10, 'GET'), '->find() accepts double quotes in URLs');
        $this->assertCount(1, self::$storage->find('127.0.0.1', 'http://foo\\bar/', 10, 'GET'), '->find() accepts backslash in URLs');
        $this->assertCount(1, self::$storage->find('127.0.0.1', 'http://foo.bar/%', 10, 'GET'), '->find() does not interpret a "%" as a wildcard in the URL');
        $this->assertCount(1, self::$storage->find('127.0.0.1', 'http://foo.bar/_', 10, 'GET'), '->find() does not interpret a "_" as a wildcard in the URL');

        self::$storage->purge();
    }

    public function testRetrieveByEmptyUrlAndIp()
    {
        for ($i = 0; $i < 5; $i++) {
            $profile = new Profile('token_'.$i);
            $profile->setMethod('GET');
            self::$storage->write($profile);
        }
        $this->assertCount(5, self::$storage->find('', '', 10, 'GET'), '->find() returns all previously added records');
        self::$storage->purge();
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
}
