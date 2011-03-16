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
        self::$storage->purge();
    }

    public function testStore()
    {
        for ($i = 0; $i < 10; $i ++) {
            self::$storage->write('token_'.$i, '', 'data', '127.0.0.1', 'http://foo.bar', time());
        }
        $this->assertEquals(count(self::$storage->find('127.0.0.1', 'http://foo.bar', 20)), 10, '->write() stores data in the database');
    }

    public function testStoreSpecialCharsInUrl()
    {
        // The SQLite storage accepts special characters in URLs (Even though URLs are not
        // supposed to contain them)
        self::$storage->write('simple_quote', '', 'data', '127.0.0.1', 'http://foo.bar/\'', time());
        self::$storage->write('double_quote', '', 'data', '127.0.0.1', 'http://foo.bar/"', time());
        self::$storage->write('backslash', '', 'data', '127.0.0.1', 'http://foo.bar/\\', time());

        $this->assertTrue(false !== self::$storage->read('simple_quote'), '->write() accepts single quotes in URL');
        $this->assertTrue(false !== self::$storage->read('double_quote'), '->write() accepts double quotes in URL');
        $this->assertTrue(false !== self::$storage->read('backslash'), '->write() accpets backslash in URL');
    }

    public function testStoreDuplicateToken()
    {
        $this->assertTrue(true === self::$storage->write('token', '', 'data', '127.0.0.1', 'http://foo.bar', time()), '->write() returns true when the token is unique');
        $this->assertTrue(false === self::$storage->write('token', '', 'data', '127.0.0.1', 'http://foo.bar', time()), '->write() return false when the token is already present in the DB');
    }

    public function testRetrieveByIp()
    {
        self::$storage->write('token', '', 'data', '127.0.0.1', 'http://foo.bar', time());

        $this->assertEquals(count(self::$storage->find('127.0.0.1', '', 10)), 1, '->find() retrieve a record by IP');
        $this->assertEquals(count(self::$storage->find('127.0.%.1', '', 10)), 0, '->find() does not interpret a "%" as a wildcard in the IP');
        $this->assertEquals(count(self::$storage->find('127.0._.1', '', 10)), 0, '->find() does not interpret a "_" as a wildcard in the IP');
    }

    public function testRetrieveByUrl()
    {
        self::$storage->write('simple_quote', '', 'data', '127.0.0.1', 'http://foo.bar/\'', time());
        self::$storage->write('double_quote', '', 'data', '127.0.0.1', 'http://foo.bar/"', time());
        self::$storage->write('backslash', '', 'data', '127.0.0.1', 'http://foo\\bar/', time());
        self::$storage->write('percent', '', 'data', '127.0.0.1', 'http://foo.bar/%', time());
        self::$storage->write('underscore', '', 'data', '127.0.0.1', 'http://foo.bar/_', time());

        $this->assertEquals(count(self::$storage->find('127.0.0.1', 'http://foo.bar/\'', 10)), 1, '->find() accepts single quotes in URLs');
        $this->assertEquals(count(self::$storage->find('127.0.0.1', 'http://foo.bar/"', 10)), 1, '->find() accepts double quotes in URLs');
        $this->assertEquals(count(self::$storage->find('127.0.0.1', 'http://foo\\bar/', 10)), 1, '->find() accepts backslash in URLs');
        $this->assertEquals(count(self::$storage->find('127.0.0.1', 'http://foo.bar/%', 10)), 1, '->find() does not interpret a "%" as a wildcard in the URL');
        $this->assertEquals(count(self::$storage->find('127.0.0.1', 'http://foo.bar/_', 10)), 1, '->find() does not interpret a "_" as a wlidcard in the URL');
    }

}
