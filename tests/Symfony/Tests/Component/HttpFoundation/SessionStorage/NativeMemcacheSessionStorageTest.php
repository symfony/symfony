<?php

namespace Symfony\Tests\Component\HttpFoundation\SessionStorage;

use Symfony\Component\HttpFoundation\SessionStorage\NativeMemcacheSessionStorage;
use Symfony\Component\HttpFoundation\AttributeBag;
use Symfony\Component\HttpFoundation\FlashBag;

/**
 * Test class for NativeMemcacheSessionStorage.
 *
 * @author Drak <drak@zikula.org>
 *
 * @runTestsInSeparateProcesses
 */
class NativeMemcacheSessionStorageTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructDefaults()
    {
        if (!extension_loaded('memcache')) {
            $this->markTestSkipped('Skipped tests SQLite extension is not present');
        }

        $storage = new NativeMemcacheSessionStorage('tcp://127.0.0.1:11211?persistent=0');
        $this->assertEquals('memcache', ini_get('session.save_handler'));
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\AttributeBagInterface', $storage->getAttributes());
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\FlashBagInterface', $storage->getFlashes());
    }

    public function testSaveHandlers()
    {
        if (!extension_loaded('memcache')) {
            $this->markTestSkipped('Skipped tests SQLite extension is not present');
        }

        $attributeBag = new AttributeBag();
        $flashBag = new FlashBag();
        $storage = new NativeMemcacheSessionStorage('tcp://127.0.0.1:11211?persistent=0', array('name' => 'TESTING'), $attributeBag, $flashBag);
        $this->assertEquals('memcache', ini_get('session.save_handler'));
        $this->assertEquals('tcp://127.0.0.1:11211?persistent=0', ini_get('session.save_path'));
        $this->assertEquals('TESTING', ini_get('session.name'));
        $this->assertSame($attributeBag, $storage->getAttributes());
        $this->assertSame($flashBag, $storage->getFlashes());
    }
}
