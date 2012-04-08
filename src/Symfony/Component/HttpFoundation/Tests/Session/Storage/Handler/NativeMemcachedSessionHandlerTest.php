<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Session\Storage\Handler;

use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeMemcachedSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

/**
 * Test class for NativeMemcachedSessionHandler.
 *
 * @author Drak <drak@zikula.org>
 *
 * @runTestsInSeparateProcesses
 */
class NativeMemcachedSessionHandlerTest extends \PHPUnit_Framework_TestCase
{
    public function testSaveHandlers()
    {
        if (!extension_loaded('memcached')) {
            $this->markTestSkipped('Skipped tests memcached extension is not present');
        }

        // test takes too long if memcached server is not running
        ini_set('memcached.sess_locking', '0');

        $storage = new NativeSessionStorage(array('name' => 'TESTING'), new NativeMemcachedSessionHandler('127.0.0.1:11211'));

        if (version_compare(phpversion(), '5.4.0', '<')) {
            $this->assertEquals('memcached', $storage->getSaveHandler()->getSaveHandlerName());
            $this->assertEquals('memcached', ini_get('session.save_handler'));
        } else {
            $this->assertEquals('memcached', $storage->getSaveHandler()->getSaveHandlerName());
            $this->assertEquals('user', ini_get('session.save_handler'));
        }

        $this->assertEquals('127.0.0.1:11211', ini_get('session.save_path'));
        $this->assertEquals('TESTING', ini_get('session.name'));
    }
}

