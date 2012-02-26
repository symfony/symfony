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

use Symfony\Component\HttpKernel\Profiler\SqliteProfilerStorage;
use Symfony\Component\HttpKernel\Profiler\Profile;

class SqliteProfilerStorageTest extends \PHPUnit_Framework_TestCase
{
    protected static $dbFile;
    protected static $storage;

    public static function setUpBeforeClass()
    {
        self::$dbFile = tempnam(sys_get_temp_dir(), 'sf2_sqlite_storage');
        if (file_exists(self::$dbFile)) {
            @unlink(self::$dbFile);
        }
        self::$storage = new SqliteProfilerStorage('sqlite:'.self::$dbFile);
    }

    public static function tearDownAfterClass()
    {
        @unlink(self::$dbFile);
    }

    protected function setUp()
    {
        if (!class_exists('SQLite3') && (!class_exists('PDO') || !in_array('sqlite', \PDO::getAvailableDrivers()))) {
            $this->markTestSkipped('This test requires SQLite support in your environment');
        }
        self::$storage->purge();
    }

    public function testStore()
    {
        for ($i = 0; $i < 10; $i ++) {
            $profile = new Profile('token_'.$i);
            $profile->setIp('127.0.0.1');
            $profile->setUrl('http://foo.bar');
            self::$storage->write($profile);
        }
        $this->assertEquals(count(self::$storage->find('127.0.0.1', 'http://foo.bar', 20)), 10, '->write() stores data in the database');
    }

    public function testStoreSpecialCharsInUrl()
    {
        // The SQLite storage accepts special characters in URLs (Even though URLs are not
        // supposed to contain them)
        $profile = new Profile('simple_quote');
        $profile->setIp('127.0.0.1');
        $profile->setUrl('http://foo.bar/\'');
        self::$storage->write($profile);
        $this->assertTrue(false !== self::$storage->read('simple_quote'), '->write() accepts single quotes in URL');

        $profile = new Profile('double_quote');
        $profile->setIp('127.0.0.1');
        $profile->setUrl('http://foo.bar/"');
        self::$storage->write($profile);
        $this->assertTrue(false !== self::$storage->read('double_quote'), '->write() accepts double quotes in URL');

        $profile = new Profile('backslash');
        $profile->setIp('127.0.0.1');
        $profile->setUrl('http://foo.bar/\\');
        self::$storage->write($profile);
        $this->assertTrue(false !== self::$storage->read('backslash'), '->write() accpets backslash in URL');
    }

    public function testStoreDuplicateToken()
    {
        $profile = new Profile('token');
        $profile->setUrl('http://example.com/');

        $this->assertTrue(self::$storage->write($profile), '->write() returns true when the token is unique');

        $profile->setUrl('http://example.net/');

        $this->assertTrue(self::$storage->write($profile), '->write() returns true when the token is already present in the DB');
        $this->assertEquals('http://example.net/', self::$storage->read('token')->getUrl(), '->write() overwrites the current profile data');
    }

    public function testRetrieveByIp()
    {
        $profile = new Profile('token');
        $profile->setIp('127.0.0.1');

        self::$storage->write($profile);

        $this->assertEquals(count(self::$storage->find('127.0.0.1', '', 10)), 1, '->find() retrieve a record by IP');
        $this->assertEquals(count(self::$storage->find('127.0.%.1', '', 10)), 0, '->find() does not interpret a "%" as a wildcard in the IP');
        $this->assertEquals(count(self::$storage->find('127.0._.1', '', 10)), 0, '->find() does not interpret a "_" as a wildcard in the IP');
    }

    public function testRetrieveByUrl()
    {
        $profile = new Profile('simple_quote');
        $profile->setIp('127.0.0.1');
        $profile->setUrl('http://foo.bar/\'');
        self::$storage->write($profile);

        $profile = new Profile('double_quote');
        $profile->setIp('127.0.0.1');
        $profile->setUrl('http://foo.bar/"');
        self::$storage->write($profile);

        $profile = new Profile('backslash');
        $profile->setIp('127.0.0.1');
        $profile->setUrl('http://foo\\bar/');
        self::$storage->write($profile);

        $profile = new Profile('percent');
        $profile->setIp('127.0.0.1');
        $profile->setUrl('http://foo.bar/%');
        self::$storage->write($profile);

        $profile = new Profile('underscore');
        $profile->setIp('127.0.0.1');
        $profile->setUrl('http://foo.bar/_');
        self::$storage->write($profile);

        $this->assertEquals(count(self::$storage->find('127.0.0.1', 'http://foo.bar/\'', 10)), 1, '->find() accepts single quotes in URLs');
        $this->assertEquals(count(self::$storage->find('127.0.0.1', 'http://foo.bar/"', 10)), 1, '->find() accepts double quotes in URLs');
        $this->assertEquals(count(self::$storage->find('127.0.0.1', 'http://foo\\bar/', 10)), 1, '->find() accepts backslash in URLs');
        $this->assertEquals(count(self::$storage->find('127.0.0.1', 'http://foo.bar/%', 10)), 1, '->find() does not interpret a "%" as a wildcard in the URL');
        $this->assertEquals(count(self::$storage->find('127.0.0.1', 'http://foo.bar/_', 10)), 1, '->find() does not interpret a "_" as a wildcard in the URL');
    }
}
