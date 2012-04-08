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

use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeSqliteSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

/**
 * Test class for NativeSqliteSessionHandler.
 *
 * @author Drak <drak@zikula.org>
 *
 * @runTestsInSeparateProcesses
 */
class NativeSqliteSessionHandlerTest extends \PHPUnit_Framework_TestCase
{
    public function testSaveHandlers()
    {
        if (!extension_loaded('sqlite')) {
            $this->markTestSkipped('Skipped tests SQLite extension is not present');
        }

        $storage = new NativeSessionStorage(array('name' => 'TESTING'), new NativeSqliteSessionHandler(sys_get_temp_dir().'/sqlite.db'));

        if (version_compare(phpversion(), '5.4.0', '<')) {
            $this->assertEquals('sqlite', $storage->getSaveHandler()->getSaveHandlerName());
            $this->assertEquals('sqlite', ini_get('session.save_handler'));
        } else {
            $this->assertEquals('sqlite', $storage->getSaveHandler()->getSaveHandlerName());
            $this->assertEquals('user', ini_get('session.save_handler'));
        }


        $this->assertEquals(sys_get_temp_dir().'/sqlite.db', ini_get('session.save_path'));
        $this->assertEquals('TESTING', ini_get('session.name'));
    }
}

