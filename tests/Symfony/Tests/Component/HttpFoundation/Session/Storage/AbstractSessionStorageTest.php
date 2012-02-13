<?php

namespace Symfony\Tests\Component\HttpFoundation\Session\Storage;

use Symfony\Component\HttpFoundation\Session\Storage\AbstractSessionStorage;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Storage\SessionHandlerInterface;

/**
 * Turn AbstractSessionStorage into something concrete because
 * certain mocking features are broken in PHPUnit-Mock-Objects < 1.1.2
 * @see https://github.com/sebastianbergmann/phpunit-mock-objects/issues/73
 */
class ConcreteSessionStorage extends AbstractSessionStorage
{
}

class CustomHandlerSessionStorage extends AbstractSessionStorage implements SessionHandlerInterface
{
    public function open($path, $id)
    {
    }

    public function close()
    {
    }

    public function read($id)
    {
    }

    public function write($id, $data)
    {
    }

    public function destroy($id)
    {
    }

    public function gc($lifetime)
    {
    }
}

/**
 * Test class for AbstractSessionStorage.
 *
 * @author Drak <drak@zikula.org>
 *
 * These tests require separate processes.
 *
 * @runTestsInSeparateProcesses
 */
class AbstractSessionStorageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @return AbstractSessionStorage
     */
    protected function getStorage()
    {
        $storage = new CustomHandlerSessionStorage();
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

    public function testCustomSaveHandlers()
    {
        $storage = new CustomHandlerSessionStorage();
        $this->assertEquals('user', ini_get('session.save_handler'));
    }

    public function testNativeSaveHandlers()
    {
        $storage = new ConcreteSessionStorage();
        $this->assertNotEquals('user', ini_get('session.save_handler'));
    }
}
