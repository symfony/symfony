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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Test class for MockArraySessionStorage.
 *
 * @author Drak <drak@zikula.org>
 */
class MockArraySessionStorageTest extends TestCase
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

        $this->data = [
            $this->attributes->getStorageKey() => ['foo' => 'bar'],
            $this->flashes->getStorageKey() => ['notice' => 'hello'],
        ];

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
        $this->assertEquals(['foo' => 'bar'], $this->storage->getBag('attributes')->all());
        $this->assertEquals(['notice' => 'hello'], $this->storage->getBag('flashes')->peekAll());

        $id = $this->storage->getId();
        $this->storage->regenerate(true);
        $this->assertNotEquals($id, $this->storage->getId());
        $this->assertEquals(['foo' => 'bar'], $this->storage->getBag('attributes')->all());
        $this->assertEquals(['notice' => 'hello'], $this->storage->getBag('flashes')->peekAll());
    }

    public function testGetId()
    {
        $this->assertEquals('', $this->storage->getId());
        $this->storage->start();
        $this->assertNotEquals('', $this->storage->getId());
    }

    public function testClearClearsBags()
    {
        $this->storage->clear();

        $this->assertSame([], $this->storage->getBag('attributes')->all());
        $this->assertSame([], $this->storage->getBag('flashes')->peekAll());
    }

    public function testClearStartsSession()
    {
        $this->storage->clear();

        $this->assertTrue($this->storage->isStarted());
    }

    public function testClearWithNoBagsStartsSession()
    {
        $storage = new MockArraySessionStorage();

        $storage->clear();

        $this->assertTrue($storage->isStarted());
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testUnstartedSave()
    {
        $this->storage->save();
    }
}
