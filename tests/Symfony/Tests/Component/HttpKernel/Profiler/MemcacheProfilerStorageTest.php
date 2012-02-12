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

use Symfony\Component\HttpKernel\Profiler\MemcacheProfilerStorage;
use Symfony\Component\HttpKernel\Profiler\Profile;

class DummyMemcacheProfilerStorage extends MemcacheProfilerStorage
{
    public function getMemcache()
    {
        return parent::getMemcache();
    }
}

class MemcacheProfilerStorageTest extends \PHPUnit_Framework_TestCase
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

        self::$storage = new DummyMemcacheProfilerStorage('memcache://127.0.0.1/11211', '', '', 86400);
        try {
            self::$storage->getMemcache();
        } catch(\Exception $e) {
            $this->markTestSkipped('MemcacheProfilerStorageTest requires that there is a Memcache server present on localhost');
        }

        if (self::$storage) {
            self::$storage->purge();
        }
    }

    public function testStore()
    {
        for ($i = 0; $i < 10; $i ++) {
            $profile = new Profile('token_'.$i);
            $profile->setIp('127.0.0.1');
            $profile->setUrl('http://foo.bar');
            $profile->setMethod('GET');
            self::$storage->write($profile);
        }
        $this->assertEquals(count(self::$storage->find('127.0.0.1', 'http://foo.bar', 20, 'GET')), 10, '->write() stores data in the Memcache');
    }


    public function testChildren()
    {
        $parentProfile = new Profile('token_parent');
        $parentProfile->setIp('127.0.0.1');
        $parentProfile->setUrl('http://foo.bar/parent');

        $childProfile = new Profile('token_child');
        $childProfile->setIp('127.0.0.1');
        $childProfile->setUrl('http://foo.bar/child');

        $parentProfile->addChild($childProfile);

        self::$storage->write($parentProfile);
        self::$storage->write($childProfile);

        // Load them from storage
        $parentProfile = self::$storage->read('token_parent');
        $childProfile  = self::$storage->read('token_child');

        // Check child has link to parent
        $this->assertNotNull($childProfile->getParent());
        $this->assertEquals($parentProfile->getToken(), $childProfile->getParentToken());

        // Check parent has child
        $children = $parentProfile->getChildren();
        $this->assertEquals(1, count($children));
        $this->assertEquals($childProfile->getToken(), $children[0]->getToken());
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
        $profile->setUrl('http://example.com/');

        $this->assertTrue(self::$storage->write($profile), '->write() returns true when the token is unique');

        $profile->setUrl('http://example.net/');

        $this->assertTrue(self::$storage->write($profile), '->write() returns true when the token is already present in the Memcache');
        $this->assertEquals('http://example.net/', self::$storage->read('token')->getUrl(), '->write() overwrites the current profile data');

        $this->assertCount(1, self::$storage->find('', '', 1000, ''), '->find() does not return the same profile twice');
    }

    public function testRetrieveByIp()
    {
        $profile = new Profile('token');
        $profile->setIp('127.0.0.1');
        $profile->setMethod('GET');

        self::$storage->write($profile);

        $this->assertEquals(count(self::$storage->find('127.0.0.1', '', 10, 'GET')), 1, '->find() retrieve a record by IP');
        $this->assertEquals(count(self::$storage->find('127.0.%.1', '', 10, 'GET')), 0, '->find() does not interpret a "%" as a wildcard in the IP');
        $this->assertEquals(count(self::$storage->find('127.0._.1', '', 10, 'GET')), 0, '->find() does not interpret a "_" as a wildcard in the IP');
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

        $profile = new Profile('semicolon');
        $profile->setIp('127.0.0.1');
        $profile->setUrl('http://foo.bar/;');
        $profile->setMethod('GET');
        self::$storage->write($profile);

        $this->assertEquals(count(self::$storage->find('127.0.0.1', 'http://foo.bar/\'', 10, 'GET')), 1, '->find() accepts single quotes in URLs');
        $this->assertEquals(count(self::$storage->find('127.0.0.1', 'http://foo.bar/"', 10, 'GET')), 1, '->find() accepts double quotes in URLs');
        $this->assertEquals(count(self::$storage->find('127.0.0.1', 'http://foo\\bar/', 10, 'GET')), 1, '->find() accepts backslash in URLs');
        $this->assertEquals(count(self::$storage->find('127.0.0.1', 'http://foo.bar/;', 10, 'GET')), 1, '->find() accepts semicolon in URLs');
        $this->assertEquals(count(self::$storage->find('127.0.0.1', 'http://foo.bar/%', 10, 'GET')), 1, '->find() does not interpret a "%" as a wildcard in the URL');
        $this->assertEquals(count(self::$storage->find('127.0.0.1', 'http://foo.bar/_', 10, 'GET')), 1, '->find() does not interpret a "_" as a wildcard in the URL');
    }
}
