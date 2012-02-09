<?php

namespace Symfony\Tests\Component\HttpFoundation\Session\Storage;

use Symfony\Component\HttpFoundation\Session\Storage\AbstractStorage;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Storage\SaveHandlerInterface;

/**
 * Turn AbstractStorage into something concrete because
 * certain mocking features are broken in PHPUnit-Mock-Objects < 1.1.2
 * @see https://github.com/sebastianbergmann/phpunit-mock-objects/issues/73
 */
class ConcreteStorage extends AbstractStorage
{
}

class CustomHandlerStorage extends AbstractStorage implements SaveHandlerInterface
{
    public function openSession($path, $id)
    {
    }

    public function closeSession()
    {
    }

    public function readSession($id)
    {
    }

    public function writeSession($id, $data)
    {
    }

    public function destroySession($id)
    {
    }

    public function gcSession($lifetime)
    {
    }
}

/**
 * Test class for AbstractStorage.
 *
 * @author Drak <drak@zikula.org>
 *
 * These tests require separate processes.
 *
 * @runTestsInSeparateProcesses
 */
class AbstractStorageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @return AbstractStorage
     */
    protected function getStorage()
    {
        $storage = new CustomHandlerStorage();
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
        $storage = new CustomHandlerStorage();
        $this->assertEquals('user', ini_get('session.save_handler'));
    }

    public function testNativeSaveHandlers()
    {
        $storage = new ConcreteStorage();
        $this->assertNotEquals('user', ini_get('session.save_handler'));
    }
}
