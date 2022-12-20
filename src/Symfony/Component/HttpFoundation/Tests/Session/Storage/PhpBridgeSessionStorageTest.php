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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Storage\PhpBridgeSessionStorage;

/**
 * Test class for PhpSessionStorage.
 *
 * @author Drak <drak@zikula.org>
 *
 * These tests require separate processes.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class PhpBridgeSessionStorageTest extends TestCase
{
    private $savePath;

    protected function setUp(): void
    {
        self::iniSet('session.save_handler', 'files');
        self::iniSet('session.save_path', $this->savePath = sys_get_temp_dir().'/sftest');
        if (!is_dir($this->savePath)) {
            mkdir($this->savePath);
        }
    }

    protected function tearDown(): void
    {
        session_write_close();
        array_map('unlink', glob($this->savePath.'/*'));
        if (is_dir($this->savePath)) {
            @rmdir($this->savePath);
        }

        $this->savePath = null;
    }

    protected function getStorage(): PhpBridgeSessionStorage
    {
        $storage = new PhpBridgeSessionStorage();
        $storage->registerBag(new AttributeBag());

        return $storage;
    }

    public function testPhpSession()
    {
        $storage = $this->getStorage();

        self::assertNotSame(\PHP_SESSION_ACTIVE, session_status());
        self::assertFalse($storage->isStarted());

        session_start();
        self::assertTrue(isset($_SESSION));
        self::assertSame(\PHP_SESSION_ACTIVE, session_status());
        // PHP session might have started, but the storage driver has not, so false is correct here
        self::assertFalse($storage->isStarted());

        $key = $storage->getMetadataBag()->getStorageKey();
        self::assertArrayNotHasKey($key, $_SESSION);
        $storage->start();
        self::assertArrayHasKey($key, $_SESSION);
    }

    public function testClear()
    {
        $storage = $this->getStorage();
        session_start();
        $_SESSION['drak'] = 'loves symfony';
        $storage->getBag('attributes')->set('symfony', 'greatness');
        $key = $storage->getBag('attributes')->getStorageKey();
        self::assertEquals(['symfony' => 'greatness'], $_SESSION[$key]);
        self::assertEquals('loves symfony', $_SESSION['drak']);
        $storage->clear();
        self::assertEquals([], $_SESSION[$key]);
        self::assertEquals('loves symfony', $_SESSION['drak']);
    }
}
