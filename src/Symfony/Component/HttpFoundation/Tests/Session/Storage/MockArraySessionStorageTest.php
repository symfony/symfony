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

    protected function setUp(): void
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

    protected function tearDown(): void
    {
        $this->data = null;
        $this->flashes = null;
        $this->attributes = null;
        $this->storage = null;
    }

    public function testStart()
    {
        self::assertEquals('', $this->storage->getId());
        $this->storage->start();
        $id = $this->storage->getId();
        self::assertNotEquals('', $id);
        $this->storage->start();
        self::assertEquals($id, $this->storage->getId());
    }

    public function testRegenerate()
    {
        $this->storage->start();
        $id = $this->storage->getId();
        $this->storage->regenerate();
        self::assertNotEquals($id, $this->storage->getId());
        self::assertEquals(['foo' => 'bar'], $this->storage->getBag('attributes')->all());
        self::assertEquals(['notice' => 'hello'], $this->storage->getBag('flashes')->peekAll());

        $id = $this->storage->getId();
        $this->storage->regenerate(true);
        self::assertNotEquals($id, $this->storage->getId());
        self::assertEquals(['foo' => 'bar'], $this->storage->getBag('attributes')->all());
        self::assertEquals(['notice' => 'hello'], $this->storage->getBag('flashes')->peekAll());
    }

    public function testGetId()
    {
        self::assertEquals('', $this->storage->getId());
        $this->storage->start();
        self::assertNotEquals('', $this->storage->getId());
    }

    public function testClearClearsBags()
    {
        $this->storage->clear();

        self::assertSame([], $this->storage->getBag('attributes')->all());
        self::assertSame([], $this->storage->getBag('flashes')->peekAll());
    }

    public function testClearStartsSession()
    {
        $this->storage->clear();

        self::assertTrue($this->storage->isStarted());
    }

    public function testClearWithNoBagsStartsSession()
    {
        $storage = new MockArraySessionStorage();

        $storage->clear();

        self::assertTrue($storage->isStarted());
    }

    public function testUnstartedSave()
    {
        self::expectException(\RuntimeException::class);
        $this->storage->save();
    }
}
