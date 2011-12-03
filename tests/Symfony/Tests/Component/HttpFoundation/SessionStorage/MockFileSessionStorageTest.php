<?php

namespace Symfony\Test\Component\HttpFoundation\SessionStorage;

use Symfony\Component\HttpFoundation\SessionStorage\MockFileSessionStorage;
use Symfony\Component\HttpFoundation\FlashBag;
use Symfony\Component\HttpFoundation\FlashBagInterface;
use Symfony\Component\HttpFoundation\AttributeBag;
use Symfony\Component\HttpFoundation\AttributeBagInterface;

/**
 * Test class for MockFileSessionStorage.
 *
 * @author Drak <drak@zikula.org>
 */
class MockFileSessionStorageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    private $sessionDir;

    /**
     * @var FileMockSessionStorage
     */
    protected $storage;

    protected function setUp()
    {
        $this->sessionDir = sys_get_temp_dir().'/sf2test';
        $this->storage = $this->getStorage();
    }

    protected function tearDown()
    {
        $this->sessionDir = null;
        $this->storage = null;
        array_map('unlink', glob($this->sessionDir.'/*.session'));
        if (is_dir($this->sessionDir)) {
            rmdir($this->sessionDir);
        }
    }

    public function testStart()
    {
        $this->assertEquals('', $this->storage->getId());
        $this->assertTrue($this->storage->start());
        $id = $this->storage->getId();
        $this->assertNotEquals('', $this->storage->getId());
        $this->assertTrue($this->storage->start());
        $this->assertEquals($id, $this->storage->getId());
    }

    public function testRegenerate()
    {
        $this->storage->start();
        $this->storage->getAttributes()->set('regenerate', 1234);
        $this->storage->regenerate();
        $this->assertEquals(1234, $this->storage->getAttributes()->get('regenerate'));
        $this->storage->regenerate(true);
        $this->assertEquals(1234, $this->storage->getAttributes()->get('regenerate'));
    }

    public function testGetId()
    {
        $this->assertEquals('', $this->storage->getId());
        $this->storage->start();
        $this->assertNotEquals('', $this->storage->getId());
    }

    public function testSave()
    {
        $this->storage->start();
        $this->assertNotEquals('108', $this->storage->getAttributes()->get('new'));
        $this->assertFalse($this->storage->getFlashes()->has('newkey'));
        $this->storage->getAttributes()->set('new', '108');
        $this->storage->getFlashes()->add('test', 'newkey');
        $this->storage->save();

        $storage = $this->getStorage();
        $storage->start();
        $this->assertEquals('108', $storage->getAttributes()->get('new'));
        $this->assertTrue($storage->getFlashes()->has('newkey'));
        $this->assertEquals(array('test'), $storage->getFlashes()->get('newkey'));
    }

    public function testMultipleInstances()
    {
        $storage1 = $this->getStorage();
        $storage1->start();
        $storage1->getAttributes()->set('foo', 'bar');
        $storage1->save();

        $storage2 = $this->getStorage();
        $storage2->start();
        $this->assertEquals('bar', $storage2->getAttributes()->get('foo'), 'values persist between instances');
    }

    private function getStorage(array $options = array())
    {
        return new MockFileSessionStorage($this->sessionDir, $options, new AttributeBag(), new FlashBag());
    }
}
