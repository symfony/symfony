<?php

namespace Symfony\Tests\Component\HttpFoundation\SessionStorage;

use Symfony\Component\HttpFoundation\SessionStorage\NativeFileSessionStorage;
use Symfony\Component\HttpFoundation\AttributeBag;
use Symfony\Component\HttpFoundation\FlashBag;

/**
 * Test class for NativeFileSessionStorage.
 *
 * @author Drak <drak@zikula.org>
 *
 * @runTestsInSeparateProcesses
 */
class NativeFileSessionStorageTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructDefaults()
    {
        $storage = new NativeFileSessionStorage();
        $this->assertEquals('files', ini_get('session.save_handler'));
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\AttributeBagInterface', $storage->getAttributes());
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\FlashBagInterface', $storage->getFlashes());
    }

    public function testSaveHandlers()
    {
        $attributeBag = new AttributeBag();
        $flashBag = new FlashBag();
        $storage = new NativeFileSessionStorage(sys_get_temp_dir(), array('name' => 'TESTING'), $attributeBag, $flashBag);
        $this->assertEquals('files', ini_get('session.save_handler'));
        $this->assertEquals(sys_get_temp_dir(), ini_get('session.save_path'));
        $this->assertEquals('TESTING', ini_get('session.name'));
        $this->assertSame($attributeBag, $storage->getAttributes());
        $this->assertSame($flashBag, $storage->getFlashes());
    }
}
