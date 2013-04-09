<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Session\Storage\Proxy;

use Symfony\Component\HttpFoundation\Session\Storage\Proxy\NativeProxySessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NullSessionHandler;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;

/**
 * Test class for NativeSessionStorage.
 *
 * These tests require separate processes.
 *
 * @runTestsInSeparateProcesses
 */
class NativeProxySessionStorageTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        ini_set('session.save_handler', 'files');
        ini_set('session.save_path', sys_get_temp_dir());
    }

    /**
     * @param array $options
     *
     * @return NativeProxySessionStorage
     */
    protected function getStorage()
    {
        $storage = new NativeProxySessionStorage();
        $storage->registerBag(new AttributeBag);

        return $storage;
    }

    public function testBag()
    {
        $storage = $this->getStorage();
        $bag = new FlashBag();
        $storage->registerBag($bag);
        $this->assertSame($bag, $storage->getBag($bag->getName()));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testRegisterBagException()
    {
        $storage = $this->getStorage();
        $storage->getBag('non_existing');
    }

    public function testGetId()
    {
        session_start();

        $storage = $this->getStorage();
        $this->assertEquals('', $storage->getId());
        $storage->start();
        $this->assertNotEquals('', $storage->getId());
    }

    public function testRegenerate()
    {
        session_start();

        $storage = $this->getStorage();
        $storage->start();
        $id = $storage->getId();
        $storage->getBag('attributes')->set('lucky', 7);
        $storage->regenerate();
        $this->assertNotEquals($id, $storage->getId());
        $this->assertEquals(7, $storage->getBag('attributes')->get('lucky'));

    }

    public function testSessionStartsIfNonActive()
    {
        if (version_compare(phpversion(), '5.4.0', '<')) {
            $this->markTestSkipped('Test skipped, for PHP 5.4+ only.');
        }

        $this->assertSame(PHP_SESSION_NONE, session_status());
        $storage = $this->getStorage();
        $storage->start();
        $this->assertSame(PHP_SESSION_ACTIVE, session_status());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetSaveHandlerFailsIfNotInstanceOfNativeProxy()
    {
        $storage = $this->getStorage();
        $storage->setSaveHandler(new NullSessionHandler());
    }

    public function testOriginalDataIsPreservedWhenClearing()
    {
        session_start();

        $_SESSION['original_data'] = 'touti';

        $storage = $this->getStorage();
        $storage->start();
        $storage->clear();

        $this->assertSame($_SESSION['original_data'], 'touti');
    }
}
