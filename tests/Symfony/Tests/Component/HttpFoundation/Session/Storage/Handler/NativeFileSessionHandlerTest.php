<?php

namespace Symfony\Tests\Component\HttpFoundation\Session\Storage\Handler;

use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

/**
 * Test class for NativeFileSessionHandler.
 *
 * @author Drak <drak@zikula.org>
 *
 * @runTestsInSeparateProcesses
 */
class NativeFileSessionHandlerTest extends \PHPUnit_Framework_TestCase
{
    public function testConstruct()
    {
        $storage = new NativeSessionStorage(array('name' => 'TESTING'), new NativeFileSessionHandler(sys_get_temp_dir()));

        if (version_compare(phpversion(), '5.4.0', '<')) {
            $this->assertEquals('files', $storage->getSaveHandler()->getSaveHandlerName());
            $this->assertEquals('files', ini_get('session.save_handler'));
        } else {
            $this->assertEquals('files', $storage->getSaveHandler()->getSaveHandlerName());
            $this->assertEquals('user', ini_get('session.save_handler'));
        }

        $this->assertEquals(sys_get_temp_dir(), ini_get('session.save_path'));
        $this->assertEquals('TESTING', ini_get('session.name'));
    }

    public function testConstructDefault()
    {
        $path = ini_get('session.save_path');
        $storage = new NativeSessionStorage(array('name' => 'TESTING'), new NativeFileSessionHandler());

        $this->assertEquals($path, ini_get('session.save_path'));
    }
}
