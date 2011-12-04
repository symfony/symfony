<?php

namespace Symfony\Tests\Component\HttpFoundation\SessionStorage;

use Symfony\Component\HttpFoundation\SessionStorage\NativeMemcachedSessionStorage;
use Symfony\Component\HttpFoundation\AttributeBag;
use Symfony\Component\HttpFoundation\FlashBag;

/**
 * Test class for NativeMemcachedSessionStorage.
 *
 * @author Drak <drak@zikula.org>
 *
 * @runTestsInSeparateProcesses
 */
class NativeMemcachedSessionStorageTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructDefaults()
    {
        if (!extension_loaded('memcached')) {
            $this->markTestSkipped('Skipped tests SQLite extension is not present');
        }

        // test takes too long if memcached server is not running
        ini_set('memcached.sess_locking', '0');

        $storage = new NativeMemcachedSessionStorage('127.0.0.1:11211');
        $this->assertEquals('memcached', ini_get('session.save_handler'));
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\AttributeBagInterface', $storage->getAttributes());
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\FlashBagInterface', $storage->getFlashes());
    }

    public function testSaveHandlers()
    {
        if (!extension_loaded('memcached')) {
            $this->markTestSkipped('Skipped tests SQLite extension is not present');
        }

        $attributeBag = new AttributeBag();
        $flashBag = new FlashBag();

        // test takes too long if memcached server is not running
        ini_set('memcached.sess_locking', '0');

        $storage = new NativeMemcachedSessionStorage('127.0.0.1:11211', array('name' => 'TESTING'), $attributeBag, $flashBag);

        $this->assertEquals('memcached', ini_get('session.save_handler'));
        $this->assertEquals('127.0.0.1:11211', ini_get('session.save_path'));
        $this->assertEquals('TESTING', ini_get('session.name'));
        $this->assertSame($attributeBag, $storage->getAttributes());
        $this->assertSame($flashBag, $storage->getFlashes());
    }
}

