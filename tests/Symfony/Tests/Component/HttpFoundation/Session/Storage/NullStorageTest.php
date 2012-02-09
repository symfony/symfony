<?php

namespace Symfony\Tests\Component\HttpFoundation\Session\Storage;
use Symfony\Component\HttpFoundation\Session\Storage\NullStorage;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Test class for NullStorage.
 *
 * @author Drak <drak@zikula.org>
 *
 * @runTestsInSeparateProcesses
 */
class NullStorageTest extends \PHPUnit_Framework_TestCase
{
    public function testSaveHandlers()
    {
        $storage = new NullStorage();
        $this->assertEquals('user', ini_get('session.save_handler'));
    }

    public function testSession()
    {
        session_id('nullsessionstorage');
        $storage = new NullStorage();
        $session = new Session($storage);
        $this->assertNull($session->get('something'));
        $session->set('something', 'unique');
        $this->assertEquals('unique', $session->get('something'));
    }

    public function testNothingIsPersisted()
    {
        session_id('nullsessionstorage');
        $storage = new NullStorage();
        $session = new Session($storage);
        $session->start();
        $this->assertEquals('nullsessionstorage', $session->getId());
        $this->assertNull($session->get('something'));
    }
}

