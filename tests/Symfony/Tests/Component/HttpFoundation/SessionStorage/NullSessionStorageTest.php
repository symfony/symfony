<?php

namespace Symfony\Tests\Component\HttpFoundation\SessionStorage;
use Symfony\Component\HttpFoundation\SessionStorage\NullSessionStorage;
use Symfony\Component\HttpFoundation\AttributeBag;
use Symfony\Component\HttpFoundation\FlashBag;
use Symfony\Component\HttpFoundation\Session;

/**
 * Test class for NullSessionStorage.
 *
 * @author Drak <drak@zikula.org>
 *
 * @runTestsInSeparateProcesses
 */
class NullSessionStorageTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructDefaults()
    {
        $storage = new NullSessionStorage();
        $this->assertEquals('user', ini_get('session.save_handler'));
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\AttributeBagInterface', $storage->getAttributes());
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\FlashBagInterface', $storage->getFlashes());
    }

    public function testSaveHandlers()
    {
        $storage = new NullSessionStorage();
        $this->assertEquals('user', ini_get('session.save_handler'));
    }

    public function testSession()
    {
        session_id('nullsessionstorage');
        $storage = new NullSessionStorage();
        $session = new Session($storage);
        $this->assertNull($session->get('something'));
        $session->set('something', 'unique');
        $this->assertEquals('unique', $session->get('something'));
    }

    public function testNothingIsPersisted()
    {
        session_id('nullsessionstorage');
        $storage = new NullSessionStorage();
        $session = new Session($storage);
        $session->start();
        $this->assertEquals('nullsessionstorage', $session->getId());
        $this->assertNull($session->get('something'));
    }
}

