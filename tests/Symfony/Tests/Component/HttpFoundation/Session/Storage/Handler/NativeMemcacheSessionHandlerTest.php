<?php

namespace Symfony\Tests\Component\HttpFoundation\Session\Storage\Handler;

use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeMemcacheSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

/**
 * Test class for NativeMemcacheSessionHandler.
 *
 * @author Drak <drak@zikula.org>
 *
 * @runTestsInSeparateProcesses
 */
class NativeMemcacheSessionHandlerTest extends \PHPUnit_Framework_TestCase
{
    public function testSaveHandlers()
    {
        if (!extension_loaded('memcache')) {
            $this->markTestSkipped('Skipped tests memcache extension is not present');
        }

        $storage = new NativeSessionStorage(array('name' => 'TESTING'), new NativeMemcacheSessionHandler('tcp://127.0.0.1:11211?persistent=0'));

        if (version_compare(phpversion(), '5.4.0', '<')) {
            $this->assertEquals('memcache', $storage->getSaveHandler()->getSaveHandlerName());
            $this->assertEquals('memcache', ini_get('session.save_handler'));
        } else {
            $this->assertEquals('memcache', $storage->getSaveHandler()->getSaveHandlerName());
            $this->assertEquals('user', ini_get('session.save_handler'));
        }

        $this->assertEquals('tcp://127.0.0.1:11211?persistent=0', ini_get('session.save_path'));
        $this->assertEquals('TESTING', ini_get('session.name'));
    }
}
