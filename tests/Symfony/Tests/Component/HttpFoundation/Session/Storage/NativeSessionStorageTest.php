<?php

namespace Symfony\Tests\Component\HttpFoundation\Session\Storage;

use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NullSessionHandler;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;

/**
 * Test class for NativeSessionStorage.
 *
 * @author Drak <drak@zikula.org>
 *
 * These tests require separate processes.
 *
 * @runTestsInSeparateProcesses
 */
class NativeSessionStorageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @return NativeSessionStorage
     */
    protected function getStorage(array $options = array())
    {
        $storage = new NativeSessionStorage($options);
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
        $storage = $this->getStorage();
        $this->assertEquals('', $storage->getId());
        $storage->start();
        $this->assertNotEquals('', $storage->getId());
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

    public function testDefaultSessionCacheLimiter()
    {
        ini_set('session.cache_limiter', 'nocache');

        $storage = new NativeSessionStorage();
        $this->assertEquals('', ini_get('session.cache_limiter'));
    }

    public function testExplicitSessionCacheLimiter()
    {
        ini_set('session.cache_limiter', 'nocache');

        $storage = new NativeSessionStorage(array('cache_limiter' => 'public'));
        $this->assertEquals('public', ini_get('session.cache_limiter'));
    }

    public function testCookieOptions()
    {
        $options = array(
            'cookie_lifetime' => 123456,
            'cookie_path' => '/my/cookie/path',
            'cookie_domain' => 'symfony2.example.com',
            'cookie_secure' => true,
            'cookie_httponly' => false,
        );

        $this->getStorage($options);
        $temp = session_get_cookie_params();
        $gco = array();

        foreach ($temp as $key => $value) {
            $gco['cookie_'.$key] = $value;
        }

        $this->assertEquals($options, $gco);
    }

    public function testSetSaveHandler()
    {
        $storage = $this->getStorage();
        $storage->setSaveHandler(new \StdClass());
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Session\Storage\Proxy\NativeProxy', $storage->getSaveHandler());
    }

    public function testSetSaveHandlerPHP53()
    {
        if (version_compare(phpversion(), '5.4.0', '>=')) {
            $this->markTestSkipped('Test skipped, for PHP 5.3 only.');
        }

        $storage = $this->getStorage();
        $storage->setSaveHandler(new NativeFileSessionHandler());
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Session\Storage\Proxy\NativeProxy', $storage->getSaveHandler());
    }

    public function testSetSaveHandlerPHP54()
    {
        if (version_compare(phpversion(), '5.4.0', '<')) {
            $this->markTestSkipped('Test skipped, for PHP 5.4+ only.');
        }

        $storage = $this->getStorage();
        $storage->setSaveHandler(new NullSessionHandler());
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Session\Storage\Proxy\SessionHandlerProxy', $storage->getSaveHandler());
    }
}
