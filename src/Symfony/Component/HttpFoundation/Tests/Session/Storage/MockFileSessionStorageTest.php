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
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorage;

/**
 * Test class for MockFileSessionStorage.
 *
 * @author Drak <drak@zikula.org>
 */
class MockFileSessionStorageTest extends TestCase
{
    /**
     * @var string
     */
    private $sessionDir;

    /**
     * @var MockFileSessionStorage
     */
    protected $storage;

    protected function setUp(): void
    {
        $this->sessionDir = sys_get_temp_dir().'/sftest';
        $this->storage = $this->getStorage();
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->sessionDir.'/*'));
        if (is_dir($this->sessionDir)) {
            @rmdir($this->sessionDir);
        }
        $this->sessionDir = null;
        $this->storage = null;
    }

    public function testStart()
    {
        self::assertEquals('', $this->storage->getId());
        self::assertTrue($this->storage->start());
        $id = $this->storage->getId();
        self::assertNotEquals('', $this->storage->getId());
        self::assertTrue($this->storage->start());
        self::assertEquals($id, $this->storage->getId());
    }

    public function testRegenerate()
    {
        $this->storage->start();
        $this->storage->getBag('attributes')->set('regenerate', 1234);
        $this->storage->regenerate();
        self::assertEquals(1234, $this->storage->getBag('attributes')->get('regenerate'));
        $this->storage->regenerate(true);
        self::assertEquals(1234, $this->storage->getBag('attributes')->get('regenerate'));
    }

    public function testGetId()
    {
        self::assertEquals('', $this->storage->getId());
        $this->storage->start();
        self::assertNotEquals('', $this->storage->getId());
    }

    public function testSave()
    {
        $this->storage->start();
        $id = $this->storage->getId();
        self::assertNotEquals('108', $this->storage->getBag('attributes')->get('new'));
        self::assertFalse($this->storage->getBag('flashes')->has('newkey'));
        $this->storage->getBag('attributes')->set('new', '108');
        $this->storage->getBag('flashes')->set('newkey', 'test');
        $this->storage->save();

        $storage = $this->getStorage();
        $storage->setId($id);
        $storage->start();
        self::assertEquals('108', $storage->getBag('attributes')->get('new'));
        self::assertTrue($storage->getBag('flashes')->has('newkey'));
        self::assertEquals(['test'], $storage->getBag('flashes')->peek('newkey'));
    }

    public function testMultipleInstances()
    {
        $storage1 = $this->getStorage();
        $storage1->start();
        $storage1->getBag('attributes')->set('foo', 'bar');
        $storage1->save();

        $storage2 = $this->getStorage();
        $storage2->setId($storage1->getId());
        $storage2->start();
        self::assertEquals('bar', $storage2->getBag('attributes')->get('foo'), 'values persist between instances');
    }

    public function testSaveWithoutStart()
    {
        self::expectException(\RuntimeException::class);
        $storage1 = $this->getStorage();
        $storage1->save();
    }

    private function getStorage(): MockFileSessionStorage
    {
        $storage = new MockFileSessionStorage($this->sessionDir);
        $storage->registerBag(new FlashBag());
        $storage->registerBag(new AttributeBag());

        return $storage;
    }
}
