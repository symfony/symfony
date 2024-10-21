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
 *
 * @preserveGlobalState disabled
 */
class NativeSessionStorageTest extends TestCase
{
    private $savePath;

    private $initialSessionSaveHandler;
    private $initialSessionSavePath;

    protected function setUp(): void
    {
        $this->initialSessionSaveHandler = ini_set('session.save_handler', 'files');
        $this->initialSessionSavePath = ini_set('session.save_path', $this->savePath = sys_get_temp_dir().'/sftest');

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
        ini_set('session.save_handler', $this->initialSessionSaveHandler);
        ini_set('session.save_path', $this->initialSessionSavePath);
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
        $this->assertSame($bag, $storage->getBag($bag->getName()));
    }

    public function testRegisterBagException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $storage = $this->getStorage();
        $storage->getBag('non_existing');
    }

    public function testRegisterBagForAStartedSessionThrowsException()
    {
        $this->expectException(\LogicException::class);
        $storage = $this->getStorage();
        $storage->start();
        $storage->registerBag(new AttributeBag());
    }

    public function testGetId()
    {
        $storage = $this->getStorage();
        $this->assertSame('', $storage->getId(), 'Empty ID before starting session');

        $storage->start();
        $id = $storage->getId();
        $this->assertIsString($id);
        $this->assertNotSame('', $id);

        $storage->save();
        $this->assertSame($id, $storage->getId(), 'ID stays after saving session');
    }

    public function testRegenerate()
    {
        $storage = $this->getStorage();
        $storage->start();
        $id = $storage->getId();
        $storage->getBag('attributes')->set('lucky', 7);
        $storage->regenerate();
        $this->assertNotEquals($id, $storage->getId());
        $this->assertEquals(7, $storage->getBag('attributes')->get('lucky'));
    }

    public function testRegenerateDestroy()
    {
        $storage = $this->getStorage();
        $storage->start();
        $id = $storage->getId();
        $storage->getBag('attributes')->set('legs', 11);
        $storage->regenerate(true);
        $this->assertNotEquals($id, $storage->getId());
        $this->assertEquals(11, $storage->getBag('attributes')->get('legs'));
    }

    public function testRegenerateWithCustomLifetime()
    {
        $storage = $this->getStorage();
        $storage->start();
        $id = $storage->getId();
        $lifetime = 999999;
        $storage->getBag('attributes')->set('legs', 11);
        $storage->regenerate(false, $lifetime);
        $this->assertNotEquals($id, $storage->getId());
        $this->assertEquals(11, $storage->getBag('attributes')->get('legs'));
        $this->assertEquals($lifetime, \ini_get('session.cookie_lifetime'));
    }

    public function testSessionGlobalIsUpToDateAfterIdRegeneration()
    {
        $storage = $this->getStorage();
        $storage->start();
        $storage->getBag('attributes')->set('lucky', 7);
        $storage->regenerate();
        $storage->getBag('attributes')->set('lucky', 42);

        $this->assertEquals(42, $_SESSION['_sf2_attributes']['lucky']);
    }

    public function testRegenerationFailureDoesNotFlagStorageAsStarted()
    {
        $storage = $this->getStorage();
        $this->assertFalse($storage->regenerate());
        $this->assertFalse($storage->isStarted());
    }

    public function testDefaultSessionCacheLimiter()
    {
        $initialLimiter = ini_set('session.cache_limiter', 'nocache');

        try {
            new NativeSessionStorage();
            $this->assertEquals('', \ini_get('session.cache_limiter'));
        } finally {
            ini_set('session.cache_limiter', $initialLimiter);
        }
    }

    public function testExplicitSessionCacheLimiter()
    {
        $initialLimiter = ini_set('session.cache_limiter', 'nocache');

        try {
            new NativeSessionStorage(['cache_limiter' => 'public']);
            $this->assertEquals('public', \ini_get('session.cache_limiter'));
        } finally {
            ini_set('session.cache_limiter', $initialLimiter);
        }
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

        $this->assertEquals($options, $gco);
    }

    public function testSessionOptions()
    {
        $options = [
            'trans_sid_tags' => 'a=href',
            'cache_expire' => '200',
        ];

        $this->getStorage($options);

        $this->assertSame('a=href', \ini_get('session.trans_sid_tags'));
        $this->assertSame('200', \ini_get('session.cache_expire'));
    }

    public function testSetSaveHandler()
    {
        $initialSaveHandler = ini_set('session.save_handler', 'files');

        try {
            $storage = $this->getStorage();
            $storage->setSaveHandler();
            $this->assertInstanceOf(SessionHandlerProxy::class, $storage->getSaveHandler());
            $storage->setSaveHandler(null);
            $this->assertInstanceOf(SessionHandlerProxy::class, $storage->getSaveHandler());
            $storage->setSaveHandler(new SessionHandlerProxy(new NativeFileSessionHandler()));
            $this->assertInstanceOf(SessionHandlerProxy::class, $storage->getSaveHandler());
            $storage->setSaveHandler(new NativeFileSessionHandler());
            $this->assertInstanceOf(SessionHandlerProxy::class, $storage->getSaveHandler());
            $storage->setSaveHandler(new SessionHandlerProxy(new NullSessionHandler()));
            $this->assertInstanceOf(SessionHandlerProxy::class, $storage->getSaveHandler());
            $storage->setSaveHandler(new NullSessionHandler());
            $this->assertInstanceOf(SessionHandlerProxy::class, $storage->getSaveHandler());
        } finally {
            ini_set('session.save_handler', $initialSaveHandler);
        }
    }

    public function testStarted()
    {
        $this->expectException(\RuntimeException::class);
        $storage = $this->getStorage();

        $this->assertFalse($storage->getSaveHandler()->isActive());
        $this->assertFalse($storage->isStarted());

        session_start();
        $this->assertTrue(isset($_SESSION));
        $this->assertTrue($storage->getSaveHandler()->isActive());

        // PHP session might have started, but the storage driver has not, so false is correct here
        $this->assertFalse($storage->isStarted());

        $key = $storage->getMetadataBag()->getStorageKey();
        $this->assertArrayNotHasKey($key, $_SESSION);
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
        $this->assertSame($id, $storage->getId(), 'Same session ID after restarting');
        $this->assertSame(7, $storage->getBag('attributes')->get('lucky'), 'Data still available');
    }

    public function testCanCreateNativeSessionStorageWhenSessionAlreadyStarted()
    {
        session_start();
        $this->getStorage();

        // Assert no exception has been thrown by `getStorage()`
        $this->addToAssertionCount(1);
    }

    public function testSetSessionOptionsOnceSessionStartedIsIgnored()
    {
        session_start();
        $this->getStorage([
            'name' => 'something-else',
        ]);

        // Assert no exception has been thrown by `getStorage()`
        $this->addToAssertionCount(1);
    }

    public function testGetBagsOnceSessionStartedIsIgnored()
    {
        session_start();
        $bag = new AttributeBag();
        $bag->setName('flashes');

        $storage = $this->getStorage();
        $storage->registerBag($bag);

        $this->assertEquals($storage->getBag('flashes'), $bag);
    }

    public function testRegenerateInvalidSessionIdForNativeFileSessionHandler()
    {
        $_COOKIE[session_name()] = '&~[';
        session_id('&~[');
        $storage = new NativeSessionStorage([], new NativeFileSessionHandler());
        $started = $storage->start();

        $this->assertTrue($started);
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9,-]{22,250}$/', session_id());
        $storage->save();

        $_COOKIE[session_name()] = '&~[';
        session_id('&~[');
        $storage = new NativeSessionStorage([], new SessionHandlerProxy(new NativeFileSessionHandler()));
        $started = $storage->start();

        $this->assertTrue($started);
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9,-]{22,250}$/', session_id());
        $storage->save();

        $_COOKIE[session_name()] = '&~[';
        session_id('&~[');
        $storage = new NativeSessionStorage([], new NullSessionHandler());
        $started = $storage->start();
        $this->assertTrue($started);
        $this->assertSame('&~[', session_id());
    }

    public function testSaveHandlesNullSessionGracefully()
    {
        $storage = $this->getStorage();
        $_SESSION = null;
        $storage->save();

        $this->addToAssertionCount(1);
    }
}
