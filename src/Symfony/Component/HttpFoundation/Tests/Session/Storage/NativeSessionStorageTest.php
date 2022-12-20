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
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NullSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\Proxy\SessionHandlerProxy;

/**
 * Test class for NativeSessionStorage.
 *
 * @author Drak <drak@zikula.org>
 *
 * These tests require separate processes.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class NativeSessionStorageTest extends TestCase
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

    protected function getStorage(array $options = []): NativeSessionStorage
    {
        $storage = new NativeSessionStorage($options);
        $storage->registerBag(new AttributeBag());

        return $storage;
    }

    public function testBag()
    {
        $storage = $this->getStorage();
        $bag = new FlashBag();
        $storage->registerBag($bag);
        self::assertSame($bag, $storage->getBag($bag->getName()));
    }

    public function testRegisterBagException()
    {
        self::expectException(\InvalidArgumentException::class);
        $storage = $this->getStorage();
        $storage->getBag('non_existing');
    }

    public function testRegisterBagForAStartedSessionThrowsException()
    {
        self::expectException(\LogicException::class);
        $storage = $this->getStorage();
        $storage->start();
        $storage->registerBag(new AttributeBag());
    }

    public function testGetId()
    {
        $storage = $this->getStorage();
        self::assertSame('', $storage->getId(), 'Empty ID before starting session');

        $storage->start();
        $id = $storage->getId();
        self::assertIsString($id);
        self::assertNotSame('', $id);

        $storage->save();
        self::assertSame($id, $storage->getId(), 'ID stays after saving session');
    }

    public function testRegenerate()
    {
        $storage = $this->getStorage();
        $storage->start();
        $id = $storage->getId();
        $storage->getBag('attributes')->set('lucky', 7);
        $storage->regenerate();
        self::assertNotEquals($id, $storage->getId());
        self::assertEquals(7, $storage->getBag('attributes')->get('lucky'));
    }

    public function testRegenerateDestroy()
    {
        $storage = $this->getStorage();
        $storage->start();
        $id = $storage->getId();
        $storage->getBag('attributes')->set('legs', 11);
        $storage->regenerate(true);
        self::assertNotEquals($id, $storage->getId());
        self::assertEquals(11, $storage->getBag('attributes')->get('legs'));
    }

    public function testRegenerateWithCustomLifetime()
    {
        $storage = $this->getStorage();
        $storage->start();
        $id = $storage->getId();
        $lifetime = 999999;
        $storage->getBag('attributes')->set('legs', 11);
        $storage->regenerate(false, $lifetime);
        self::assertNotEquals($id, $storage->getId());
        self::assertEquals(11, $storage->getBag('attributes')->get('legs'));
        self::assertEquals($lifetime, \ini_get('session.cookie_lifetime'));
    }

    public function testSessionGlobalIsUpToDateAfterIdRegeneration()
    {
        $storage = $this->getStorage();
        $storage->start();
        $storage->getBag('attributes')->set('lucky', 7);
        $storage->regenerate();
        $storage->getBag('attributes')->set('lucky', 42);

        self::assertEquals(42, $_SESSION['_sf2_attributes']['lucky']);
    }

    public function testRegenerationFailureDoesNotFlagStorageAsStarted()
    {
        $storage = $this->getStorage();
        self::assertFalse($storage->regenerate());
        self::assertFalse($storage->isStarted());
    }

    public function testDefaultSessionCacheLimiter()
    {
        self::iniSet('session.cache_limiter', 'nocache');

        new NativeSessionStorage();
        self::assertEquals('', \ini_get('session.cache_limiter'));
    }

    public function testExplicitSessionCacheLimiter()
    {
        self::iniSet('session.cache_limiter', 'nocache');

        new NativeSessionStorage(['cache_limiter' => 'public']);
        self::assertEquals('public', \ini_get('session.cache_limiter'));
    }

    public function testCookieOptions()
    {
        $options = [
            'cookie_lifetime' => 123456,
            'cookie_path' => '/my/cookie/path',
            'cookie_domain' => 'symfony.example.com',
            'cookie_secure' => true,
            'cookie_httponly' => false,
        ];

        if (\PHP_VERSION_ID >= 70300) {
            $options['cookie_samesite'] = 'lax';
        }

        $this->getStorage($options);
        $temp = session_get_cookie_params();
        $gco = [];

        foreach ($temp as $key => $value) {
            $gco['cookie_'.$key] = $value;
        }

        self::assertEquals($options, $gco);
    }

    public function testSessionOptions()
    {
        $options = [
            'trans_sid_tags' => 'a=href',
            'cache_expire' => '200',
        ];

        $this->getStorage($options);

        self::assertSame('a=href', \ini_get('session.trans_sid_tags'));
        self::assertSame('200', \ini_get('session.cache_expire'));
    }

    public function testSetSaveHandler()
    {
        self::iniSet('session.save_handler', 'files');
        $storage = $this->getStorage();
        $storage->setSaveHandler();
        self::assertInstanceOf(SessionHandlerProxy::class, $storage->getSaveHandler());
        $storage->setSaveHandler(null);
        self::assertInstanceOf(SessionHandlerProxy::class, $storage->getSaveHandler());
        $storage->setSaveHandler(new SessionHandlerProxy(new NativeFileSessionHandler()));
        self::assertInstanceOf(SessionHandlerProxy::class, $storage->getSaveHandler());
        $storage->setSaveHandler(new NativeFileSessionHandler());
        self::assertInstanceOf(SessionHandlerProxy::class, $storage->getSaveHandler());
        $storage->setSaveHandler(new SessionHandlerProxy(new NullSessionHandler()));
        self::assertInstanceOf(SessionHandlerProxy::class, $storage->getSaveHandler());
        $storage->setSaveHandler(new NullSessionHandler());
        self::assertInstanceOf(SessionHandlerProxy::class, $storage->getSaveHandler());
    }

    public function testStarted()
    {
        self::expectException(\RuntimeException::class);
        $storage = $this->getStorage();

        self::assertFalse($storage->getSaveHandler()->isActive());
        self::assertFalse($storage->isStarted());

        session_start();
        self::assertTrue(isset($_SESSION));
        self::assertTrue($storage->getSaveHandler()->isActive());

        // PHP session might have started, but the storage driver has not, so false is correct here
        self::assertFalse($storage->isStarted());

        $key = $storage->getMetadataBag()->getStorageKey();
        self::assertArrayNotHasKey($key, $_SESSION);
        $storage->start();
    }

    public function testRestart()
    {
        $storage = $this->getStorage();
        $storage->start();
        $id = $storage->getId();
        $storage->getBag('attributes')->set('lucky', 7);
        $storage->save();
        $storage->start();
        self::assertSame($id, $storage->getId(), 'Same session ID after restarting');
        self::assertSame(7, $storage->getBag('attributes')->get('lucky'), 'Data still available');
    }

    public function testCanCreateNativeSessionStorageWhenSessionAlreadyStarted()
    {
        session_start();
        $this->getStorage();

        // Assert no exception has been thrown by `getStorage()`
        self::addToAssertionCount(1);
    }

    public function testSetSessionOptionsOnceSessionStartedIsIgnored()
    {
        session_start();
        $this->getStorage([
            'name' => 'something-else',
        ]);

        // Assert no exception has been thrown by `getStorage()`
        self::addToAssertionCount(1);
    }

    public function testGetBagsOnceSessionStartedIsIgnored()
    {
        session_start();
        $bag = new AttributeBag();
        $bag->setName('flashes');

        $storage = $this->getStorage();
        $storage->registerBag($bag);

        self::assertEquals($storage->getBag('flashes'), $bag);
    }

    public function testRegenerateInvalidSessionIdForNativeFileSessionHandler()
    {
        $_COOKIE[session_name()] = '&~[';
        session_id('&~[');
        $storage = new NativeSessionStorage([], new NativeFileSessionHandler());
        $started = $storage->start();

        self::assertTrue($started);
        self::assertMatchesRegularExpression('/^[a-zA-Z0-9,-]{22,250}$/', session_id());
        $storage->save();

        $_COOKIE[session_name()] = '&~[';
        session_id('&~[');
        $storage = new NativeSessionStorage([], new SessionHandlerProxy(new NativeFileSessionHandler()));
        $started = $storage->start();

        self::assertTrue($started);
        self::assertMatchesRegularExpression('/^[a-zA-Z0-9,-]{22,250}$/', session_id());
        $storage->save();

        $_COOKIE[session_name()] = '&~[';
        session_id('&~[');
        $storage = new NativeSessionStorage([], new NullSessionHandler());
        $started = $storage->start();
        self::assertTrue($started);
        self::assertSame('&~[', session_id());
    }

    public function testSaveHandlesNullSessionGracefully()
    {
        $storage = $this->getStorage();
        $_SESSION = null;
        $storage->save();

        self::addToAssertionCount(1);
    }
}
