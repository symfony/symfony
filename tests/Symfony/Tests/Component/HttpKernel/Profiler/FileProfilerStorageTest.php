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

use Symfony\Component\HttpKernel\Profiler\FileProfilerStorage;
use Symfony\Component\HttpKernel\Profiler\Profile;

class FileProfilerStorageTest extends \PHPUnit_Framework_TestCase
{
    protected static $tmpDir;
    protected static $storage;

    protected static function cleanDir()
    {
        $flags = \FilesystemIterator::SKIP_DOTS;
        $iterator = new \RecursiveDirectoryIterator(self::$tmpDir, $flags);
        $iterator = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $file)
        {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public static function setUpBeforeClass()
    {
        self::$tmpDir = sys_get_temp_dir() . '/sf2_profiler_file_storage';
        if (is_dir(self::$tmpDir)) {
            self::cleanDir();
        }
        self::$storage = new FileProfilerStorage('file:'.self::$tmpDir);
    }

    public static function tearDownAfterClass()
    {
        self::cleanDir();
    }

    protected function setUp()
    {
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
        $profile->setUrl('127.0.0.1', 'http://foo.bar/\'');
        self::$storage->write($profile);
        $this->assertTrue(false !== self::$storage->read('simple_quote'), '->write() accepts single quotes in URL');

        $profile = new Profile('double_quote');
        $profile->setUrl('127.0.0.1', 'http://foo.bar/"');
        self::$storage->write($profile);
        $this->assertTrue(false !== self::$storage->read('double_quote'), '->write() accepts double quotes in URL');

        $profile = new Profile('backslash');
        $profile->setUrl('127.0.0.1', 'http://foo.bar/\\');
        self::$storage->write($profile);
        $this->assertTrue(false !== self::$storage->read('backslash'), '->write() accepts backslash in URL');

        $profile = new Profile('comma');
        $profile->setUrl('127.0.0.1', 'http://foo.bar/,');
        self::$storage->write($profile);
        $this->assertTrue(false !== self::$storage->read('comma'), '->write() accepts comma in URL');
    }

    public function testStoreDuplicateToken()
    {
        $profile = new Profile('token');

        $this->assertTrue(true === self::$storage->write($profile), '->write() returns true when the token is unique');
        $this->assertTrue(false === self::$storage->write($profile), '->write() return false when the token is already present in the DB');
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

        $profile = new Profile('semicolon');
        $profile->setIp('127.0.0.1');
        $profile->setUrl('http://foo.bar/;');
        self::$storage->write($profile);

        $this->assertEquals(count(self::$storage->find('127.0.0.1', 'http://foo.bar/\'', 10)), 1, '->find() accepts single quotes in URLs');
        $this->assertEquals(count(self::$storage->find('127.0.0.1', 'http://foo.bar/"', 10)), 1, '->find() accepts double quotes in URLs');
        $this->assertEquals(count(self::$storage->find('127.0.0.1', 'http://foo\\bar/', 10)), 1, '->find() accepts backslash in URLs');
        $this->assertEquals(count(self::$storage->find('127.0.0.1', 'http://foo.bar/;', 10)), 1, '->find() accepts semicolon in URLs');
        $this->assertEquals(count(self::$storage->find('127.0.0.1', 'http://foo.bar/%', 10)), 1, '->find() does not interpret a "%" as a wildcard in the URL');
        $this->assertEquals(count(self::$storage->find('127.0.0.1', 'http://foo.bar/_', 10)), 1, '->find() does not interpret a "_" as a wildcard in the URL');
    }
}
