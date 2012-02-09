<?php

namespace Symfony\Tests\Component\HttpFoundation\Session\Storage;

use Symfony\Component\HttpFoundation\Session\Storage\NativeMemcacheStorage;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;

/**
 * Test class for NativeMemcacheStorage.
 *
 * @author Drak <drak@zikula.org>
 *
 * @runTestsInSeparateProcesses
 */
class NativeMemcacheStorageTest extends \PHPUnit_Framework_TestCase
{
    public function testSaveHandlers()
    {
        if (!extension_loaded('memcache')) {
            $this->markTestSkipped('Skipped tests SQLite extension is not present');
        }

        $storage = new NativeMemcacheStorage('tcp://127.0.0.1:11211?persistent=0', array('name' => 'TESTING'));
        $this->assertEquals('memcache', ini_get('session.save_handler'));
        $this->assertEquals('tcp://127.0.0.1:11211?persistent=0', ini_get('session.save_path'));
        $this->assertEquals('TESTING', ini_get('session.name'));
    }
}
