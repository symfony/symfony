<?php

namespace Symfony\Tests\Component\HttpFoundation\Session\Storage;

use Symfony\Component\HttpFoundation\Session\Storage\NativeSqliteStorage;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;

/**
 * Test class for NativeSqliteStorage.
 *
 * @author Drak <drak@zikula.org>
 *
 * @runTestsInSeparateProcesses
 */
class NativeSqliteStorageTest extends \PHPUnit_Framework_TestCase
{
    public function testSaveHandlers()
    {
        if (!extension_loaded('sqlite')) {
            $this->markTestSkipped('Skipped tests SQLite extension is not present');
        }

        $storage = new NativeSqliteStorage(sys_get_temp_dir().'/sqlite.db', array('name' => 'TESTING'));
        $this->assertEquals('sqlite', ini_get('session.save_handler'));
        $this->assertEquals(sys_get_temp_dir().'/sqlite.db', ini_get('session.save_path'));
        $this->assertEquals('TESTING', ini_get('session.name'));
    }
}

