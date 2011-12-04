<?php

namespace Symfony\Tests\Component\HttpFoundation\SessionStorage;

use Symfony\Component\HttpFoundation\SessionStorage\NativeSqliteSessionStorage;
use Symfony\Component\HttpFoundation\AttributeBag;
use Symfony\Component\HttpFoundation\FlashBag;

/**
 * Test class for NativeSqliteSessionStorage.
 *
 * @author Drak <drak@zikula.org>
 *
 * @runTestsInSeparateProcesses
 */
class NativeSqliteSessionStorageTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructDefaults()
    {
        if (!extension_loaded('sqlite')) {
            $this->markTestSkipped('Skipped tests SQLite extension is not present');
        }

        $storage = new NativeSqliteSessionStorage(sys_get_temp_dir().'/sqlite.db');
        $this->assertEquals('sqlite', ini_get('session.save_handler'));
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\AttributeBagInterface', $storage->getAttributes());
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\FlashBagInterface', $storage->getFlashes());
    }

    public function testSaveHandlers()
    {
        if (!extension_loaded('sqlite')) {
            $this->markTestSkipped('Skipped tests SQLite extension is not present');
        }

        $attributeBag = new AttributeBag();
        $flashBag = new FlashBag();
        $storage = new NativeSqliteSessionStorage(sys_get_temp_dir().'/sqlite.db', array('name' => 'TESTING'), $attributeBag, $flashBag);
        $this->assertEquals('sqlite', ini_get('session.save_handler'));
        $this->assertEquals(sys_get_temp_dir().'/sqlite.db', ini_get('session.save_path'));
        $this->assertEquals('TESTING', ini_get('session.name'));
        $this->assertSame($attributeBag, $storage->getAttributes());
        $this->assertSame($flashBag, $storage->getFlashes());
    }
}

