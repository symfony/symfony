<?php

namespace Symfony\Tests\Component\HttpFoundation\SessionStorage;

use Symfony\Component\HttpFoundation\SessionStorage\NativeMemcachedSessionStorage;
use Symfony\Component\HttpFoundation\SessionAttribute\AttributeBag;
use Symfony\Component\HttpFoundation\SessionFlash\FlashBag;

/**
 * Test class for NativeMemcachedSessionStorage.
 *
 * @author Drak <drak@zikula.org>
 *
 * @runTestsInSeparateProcesses
 */
class NativeMemcachedSessionStorageTest extends \PHPUnit_Framework_TestCase
{
    public function testSaveHandlers()
    {
        if (!extension_loaded('memcached')) {
            $this->markTestSkipped('Skipped tests SQLite extension is not present');
        }

        // test takes too long if memcached server is not running
        ini_set('memcached.sess_locking', '0');

        $storage = new NativeMemcachedSessionStorage('127.0.0.1:11211', array('name' => 'TESTING'));

        $this->assertEquals('memcached', ini_get('session.save_handler'));
        $this->assertEquals('127.0.0.1:11211', ini_get('session.save_path'));
        $this->assertEquals('TESTING', ini_get('session.name'));
    }
}

