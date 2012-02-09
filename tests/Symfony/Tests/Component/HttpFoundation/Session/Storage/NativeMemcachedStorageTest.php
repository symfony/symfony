<?php

namespace Symfony\Tests\Component\HttpFoundation\Session\Storage;

use Symfony\Component\HttpFoundation\Session\Storage\NativeMemcachedStorage;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;

/**
 * Test class for NativeMemcachedStorage.
 *
 * @author Drak <drak@zikula.org>
 *
 * @runTestsInSeparateProcesses
 */
class NativeMemcachedStorageTest extends \PHPUnit_Framework_TestCase
{
    public function testSaveHandlers()
    {
        if (!extension_loaded('memcached')) {
            $this->markTestSkipped('Skipped tests SQLite extension is not present');
        }

        // test takes too long if memcached server is not running
        ini_set('memcached.sess_locking', '0');

        $storage = new NativeMemcachedStorage('127.0.0.1:11211', array('name' => 'TESTING'));

        $this->assertEquals('memcached', ini_get('session.save_handler'));
        $this->assertEquals('127.0.0.1:11211', ini_get('session.save_path'));
        $this->assertEquals('TESTING', ini_get('session.name'));
    }
}

