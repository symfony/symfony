<?php

namespace Symfony\Tests\Component\HttpFoundation\SessionStorage;

use Symfony\Component\HttpFoundation\SessionStorage\ArraySessionStorage;
use Symfony\Component\HttpFoundation\AttributeBag;
use Symfony\Component\HttpFoundation\FlashBag;


/**
 * Test class for ArraySessionStorage.
 *
 * @author Drak <drak@zikula.org>
 */
class ArraySessionStorageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ArraySessionStorage
     */
    private $storage;

    /**
     * @var FlashBag
     */
    private $flashBag;

    /**
     * @var AttributeBag
     */
    private $attributeBag;

    private $attributes;
    private $flashes;

    protected function setUp()
    {
        $this->attributes = array('foo' => 'bar');
        $this->flashes = array('notice' => 'hello');
        $this->flashBag = new FlashBag();
        $this->flashBag->initialize($this->flashes);
        $this->attributeBag = new AttributeBag();
        $this->attributeBag->initialize($this->attributes);
        $this->storage = new ArraySessionStorage($this->attributeBag, $this->flashBag);
    }

    protected function tearDown()
    {
        $this->flashBag = null;
        $this->attributesBag = null;
        $this->storage = null;
    }

    public function testStart()
    {
        $this->assertEquals('', $this->storage->getId());
        $this->storage->start();
        $id = $this->storage->getId();
        $this->assertNotEquals('', $id);
        $this->storage->start();
        $this->assertEquals($id, $this->storage->getId());
    }

    public function testRegenerateDestroy()
    {
        $this->storage->start();
        $id = $this->storage->getId();
        $this->storage->regenerate(true);
        $this->assertNotEquals($id, $this->storage->getId());
        $this->assertEquals(array(), $this->storage->getAttributes()->all());
        $this->assertEquals(array(), $this->storage->getFlashes()->all());
    }

    public function testRegenerate()
    {
        $this->storage->start();
        $id = $this->storage->getId();
        $this->storage->regenerate();
        $this->assertNotEquals($id, $this->storage->getId());

        $this->assertEquals($this->attributes, $this->storage->getAttributes()->all());
        $this->assertEquals($this->flashes, $this->storage->getFlashes()->all());
    }

    public function testGetId()
    {
        $this->assertEquals('', $this->storage->getId());
        $this->storage->start();
        $this->assertNotEquals('', $this->storage->getId());
    }
}
