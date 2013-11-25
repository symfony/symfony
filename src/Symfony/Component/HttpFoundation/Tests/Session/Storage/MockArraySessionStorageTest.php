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

use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;

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
     * @var AttributeBag
     */
    private $attributes;

    /**
     * @var FlashBag
     */
    private $flashes;

    private $data;

    protected function setUp()
    {
        $this->attributes = new AttributeBag();
        $this->flashes = new FlashBag();

        $this->data = array(
            $this->attributes->getStorageKey() => array('foo' => 'bar'),
            $this->flashes->getStorageKey() => array('notice' => 'hello'),
            );

        $this->storage = new MockArraySessionStorage();
        $this->storage->registerBag($this->flashes);
        $this->storage->registerBag($this->attributes);
        $this->storage->setSessionData($this->data);
    }

    protected function tearDown()
    {
        $this->data = null;
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
        $this->assertEquals(array('foo' => 'bar'), $this->storage->getBag('attributes')->all());
        $this->assertEquals(array('notice' => 'hello'), $this->storage->getBag('flashes')->peekAll());

        $id = $this->storage->getId();
        $this->storage->regenerate(true);
        $this->assertNotEquals($id, $this->storage->getId());
        $this->assertEquals(array('foo' => 'bar'), $this->storage->getBag('attributes')->all());
        $this->assertEquals(array('notice' => 'hello'), $this->storage->getBag('flashes')->peekAll());
    }

    public function testGetId()
    {
        $this->assertEquals('', $this->storage->getId());
        $this->storage->start();
        $this->assertNotEquals('', $this->storage->getId());
    }

    /**
     * @expectedException RuntimeException
     */
    public function testUnstartedSave()
    {
        $this->storage->save();
    }

    public function testClosedIsStarted()
    {
        $this->storage->start();

        $refl = new \ReflectionProperty($this->storage, 'started');
        $refl->setAccessible(true);

        $this->assertTrue($this->storage->isStarted());
        $this->assertTrue($refl->getValue($this->storage));

        $this->storage->save();

        // isStarted should return false once the storage saves the session
        $this->assertFalse($this->storage->isStarted());
        $this->assertFalse($refl->getValue($this->storage));
    }
}
