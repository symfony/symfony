<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Session\Storage;

use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeRedisSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

/**
 * Test class for NativeRedisSessionHandlerTest.
 *
 * @runTestsInSeparateProcesses
 */
class NativeRedisSessionHandlerTest extends \PHPUnit_Framework_TestCase
{
    public function testSaveHandlers()
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Skipped tests Redis extension is not present');
        }

        $storage = new NativeSessionStorage(array('name' => 'TESTING'), new NativeRedisSessionHandler('tcp://127.0.0.1:6379?persistent=0'));

        if (version_compare(phpversion(), '5.4.0', '<')) {
            $this->assertEquals('redis', $storage->getSaveHandler()->getSaveHandlerName());
            $this->assertEquals('redis', ini_get('session.save_handler'));
        } else {
            $this->assertEquals('redis', $storage->getSaveHandler()->getSaveHandlerName());
            $this->assertEquals('user', ini_get('session.save_handler'));
        }

        $this->assertEquals('tcp://127.0.0.1:6379?persistent=0', ini_get('session.save_path'));
        $this->assertEquals('TESTING', ini_get('session.name'));
    }
}
