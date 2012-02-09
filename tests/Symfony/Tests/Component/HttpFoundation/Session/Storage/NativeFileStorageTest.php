<?php

namespace Symfony\Tests\Component\HttpFoundation\Session\Storage;

use Symfony\Component\HttpFoundation\Session\Storage\NativeFileStorage;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;

/**
 * Test class for NativeFileStorage.
 *
 * @author Drak <drak@zikula.org>
 *
 * @runTestsInSeparateProcesses
 */
class NativeFileStorageTest extends \PHPUnit_Framework_TestCase
{
    public function testSaveHandlers()
    {
        $storage = new NativeFileStorage(sys_get_temp_dir(), array('name' => 'TESTING'));
        $this->assertEquals('files', ini_get('session.save_handler'));
        $this->assertEquals(sys_get_temp_dir(), ini_get('session.save_path'));
        $this->assertEquals('TESTING', ini_get('session.name'));
    }
}
