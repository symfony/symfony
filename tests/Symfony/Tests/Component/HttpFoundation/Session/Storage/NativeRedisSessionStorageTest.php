<?php

namespace Symfony\Tests\Component\HttpFoundation\Session\Storage;

use Symfony\Component\HttpFoundation\Session\Storage\NativeRedisSessionStorage;

/**
 * Test class for NativeRedisSessionStorage.
 *
 * @runTestsInSeparateProcesses
 */
class NativeRedisSessionStorageTest extends \PHPUnit_Framework_TestCase
{
    public function testSaveHandlers()
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Skipped tests - Redis extension is not present');
        }

        $storage = new NativeRedisSessionStorage('tcp://127.0.0.1:6379?persistent=0', array('name' => 'TESTING'));
        $this->assertEquals('redis', ini_get('session.save_handler'));
        $this->assertEquals('tcp://127.0.0.1:6379?persistent=0', ini_get('session.save_path'));
        $this->assertEquals('TESTING', ini_get('session.name'));
    }
}