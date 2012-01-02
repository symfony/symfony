<?php

namespace Symfony\Tests\Component\HttpFoundation\SessionStorage;

use Symfony\Component\HttpFoundation\SessionStorage\MockArraySessionStorage;
use Symfony\Component\HttpFoundation\AttributeBag;
use Symfony\Component\HttpFoundation\FlashBag;


/**
 * Test class for MockArraySessionStorage.
 *
 * @author Drak <drak@zikula.org>
 */
class MockArraySessionStorageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var MockArraySessionStorage
     */
    private $storage;

    /**
     * @var array
     */
    private $attributes;

    /**
     * @var array
     */
    private $flashes;

    protected function setUp()
    {
        $this->attributes = array('foo' => 'bar');
        $this->flashes = array('notice' => 'hello');
        $this->storage = new MockArraySessionStorage(new AttributeBag(), new FlashBag());
        $this->storage->setFlashes($this->flashes);
        $this->storage->setAttributes($this->attributes);
    }

    protected function tearDown()
    {
        $this->flashes = null;
        $this->attributes = null;
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

    public function testRegenerate()
    {
        $this->storage->start();
        $id = $this->storage->getId();
        $this->storage->regenerate();
        $this->assertNotEquals($id, $this->storage->getId());
        $this->assertEquals($this->attributes, $this->storage->getAttributes()->all());
        $this->assertEquals($this->flashes, $this->storage->getFlashes()->all());

        $id = $this->storage->getId();
        $this->storage->regenerate(true);
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
