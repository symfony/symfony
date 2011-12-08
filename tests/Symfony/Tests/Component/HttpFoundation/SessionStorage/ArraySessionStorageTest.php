<?php

namespace Symfony\Tests\Component\HttpFoundation\SessionStorage;

use Symfony\Component\HttpFoundation\SessionStorage\ArraySessionStorage;
use Symfony\Component\HttpFoundation\AttributesBag;
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
     * @var AttributesBag
     */
    private $attributesBag;

    private $attributes;
    private $flashes;

    protected function setUp()
    {
        $this->attributes = array('foo' => 'bar');
        $this->flashes = array('notice' => 'hello');
        $this->flashBag = new FlashBag;
        $this->flashBag->initialize($this->flashes);
        $this->attributesBag = new AttributesBag;
        $this->attributesBag->initialize($this->attributes);
        $this->storage = new ArraySessionStorage();
        $this->storage->setFlashBag($this->flashBag);
        $this->storage->setAttributesBag($this->attributesBag);
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
        $this->assertEquals(array(), $this->storage->getAttributesBag()->all());
        $this->assertEquals(array(), $this->storage->getFlashBag()->all());
    }

    public function testRegenerate()
    {
        $this->storage->start();
        $id = $this->storage->getId();
        $this->storage->regenerate();
        $this->assertNotEquals($id, $this->storage->getId());

        $this->assertEquals($this->attributes, $this->storage->getAttributesBag()->all());
        $this->assertEquals($this->flashes, $this->storage->getFlashBag()->all());
    }

    public function testGetId()
    {
        $this->assertEquals('', $this->storage->getId());
        $this->storage->start();
        $this->assertNotEquals('', $this->storage->getId());
    }
}
