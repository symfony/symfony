<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Session;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionBagProxy;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * SessionTest.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Robert Schönthal <seroscho@googlemail.com>
 * @author Drak <drak@zikula.org>
 */
class SessionTest extends TestCase
{
    /**
     * @var \Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface
     */
    protected $storage;

    /**
     * @var \Symfony\Component\HttpFoundation\Session\SessionInterface
     */
    protected $session;

    protected function setUp(): void
    {
        $this->storage = new MockArraySessionStorage();
        $this->session = new Session($this->storage, new AttributeBag(), new FlashBag());
    }

    protected function tearDown(): void
    {
        $this->storage = null;
        $this->session = null;
    }

    public function testStart()
    {
        self::assertEquals('', $this->session->getId());
        self::assertTrue($this->session->start());
        self::assertNotEquals('', $this->session->getId());
    }

    public function testIsStarted()
    {
        self::assertFalse($this->session->isStarted());
        $this->session->start();
        self::assertTrue($this->session->isStarted());
    }

    public function testSetId()
    {
        self::assertEquals('', $this->session->getId());
        $this->session->setId('0123456789abcdef');
        $this->session->start();
        self::assertEquals('0123456789abcdef', $this->session->getId());
    }

    public function testSetIdAfterStart()
    {
        $this->session->start();
        $id = $this->session->getId();

        $e = null;
        try {
            $this->session->setId($id);
        } catch (\Exception $e) {
        }

        self::assertNull($e);

        try {
            $this->session->setId('different');
        } catch (\Exception $e) {
        }

        self::assertInstanceOf(\LogicException::class, $e);
    }

    public function testSetName()
    {
        self::assertEquals('MOCKSESSID', $this->session->getName());
        $this->session->setName('session.test.com');
        $this->session->start();
        self::assertEquals('session.test.com', $this->session->getName());
    }

    public function testGet()
    {
        // tests defaults
        self::assertNull($this->session->get('foo'));
        self::assertEquals(1, $this->session->get('foo', 1));
    }

    /**
     * @dataProvider setProvider
     */
    public function testSet($key, $value)
    {
        $this->session->set($key, $value);
        self::assertEquals($value, $this->session->get($key));
    }

    /**
     * @dataProvider setProvider
     */
    public function testHas($key, $value)
    {
        $this->session->set($key, $value);
        self::assertTrue($this->session->has($key));
        self::assertFalse($this->session->has($key.'non_value'));
    }

    public function testReplace()
    {
        $this->session->replace(['happiness' => 'be good', 'symfony' => 'awesome']);
        self::assertEquals(['happiness' => 'be good', 'symfony' => 'awesome'], $this->session->all());
        $this->session->replace([]);
        self::assertEquals([], $this->session->all());
    }

    /**
     * @dataProvider setProvider
     */
    public function testAll($key, $value, $result)
    {
        $this->session->set($key, $value);
        self::assertEquals($result, $this->session->all());
    }

    /**
     * @dataProvider setProvider
     */
    public function testClear($key, $value)
    {
        $this->session->set('hi', 'fabien');
        $this->session->set($key, $value);
        $this->session->clear();
        self::assertEquals([], $this->session->all());
    }

    public function setProvider()
    {
        return [
            ['foo', 'bar', ['foo' => 'bar']],
            ['foo.bar', 'too much beer', ['foo.bar' => 'too much beer']],
            ['great', 'symfony is great', ['great' => 'symfony is great']],
        ];
    }

    /**
     * @dataProvider setProvider
     */
    public function testRemove($key, $value)
    {
        $this->session->set('hi.world', 'have a nice day');
        $this->session->set($key, $value);
        $this->session->remove($key);
        self::assertEquals(['hi.world' => 'have a nice day'], $this->session->all());
    }

    public function testInvalidate()
    {
        $this->session->set('invalidate', 123);
        $this->session->invalidate();
        self::assertEquals([], $this->session->all());
    }

    public function testMigrate()
    {
        $this->session->set('migrate', 321);
        $this->session->migrate();
        self::assertEquals(321, $this->session->get('migrate'));
    }

    public function testMigrateDestroy()
    {
        $this->session->set('migrate', 333);
        $this->session->migrate(true);
        self::assertEquals(333, $this->session->get('migrate'));
    }

    public function testSave()
    {
        $this->session->start();
        $this->session->save();

        self::assertFalse($this->session->isStarted());
    }

    public function testGetId()
    {
        self::assertEquals('', $this->session->getId());
        $this->session->start();
        self::assertNotEquals('', $this->session->getId());
    }

    public function testGetFlashBag()
    {
        self::assertInstanceOf(FlashBagInterface::class, $this->session->getFlashBag());
    }

    public function testGetIterator()
    {
        $attributes = ['hello' => 'world', 'symfony' => 'rocks'];
        foreach ($attributes as $key => $val) {
            $this->session->set($key, $val);
        }

        $i = 0;
        foreach ($this->session as $key => $val) {
            self::assertEquals($attributes[$key], $val);
            ++$i;
        }

        self::assertEquals(\count($attributes), $i);
    }

    public function testGetCount()
    {
        $this->session->set('hello', 'world');
        $this->session->set('symfony', 'rocks');

        self::assertCount(2, $this->session);
    }

    public function testGetMeta()
    {
        self::assertInstanceOf(MetadataBag::class, $this->session->getMetadataBag());
    }

    public function testIsEmpty()
    {
        self::assertTrue($this->session->isEmpty());

        $this->session->set('hello', 'world');
        self::assertFalse($this->session->isEmpty());

        $this->session->remove('hello');
        self::assertTrue($this->session->isEmpty());

        $flash = $this->session->getFlashBag();
        $flash->set('hello', 'world');
        self::assertFalse($this->session->isEmpty());

        $flash->get('hello');
        self::assertTrue($this->session->isEmpty());
    }

    public function testGetBagWithBagImplementingGetBag()
    {
        $bag = new AttributeBag();
        $bag->setName('foo');

        $storage = new MockArraySessionStorage();
        $storage->registerBag($bag);

        self::assertSame($bag, (new Session($storage))->getBag('foo'));
    }

    public function testGetBagWithBagNotImplementingGetBag()
    {
        $data = [];

        $bag = new AttributeBag();
        $bag->setName('foo');

        $storage = new MockArraySessionStorage();
        $storage->registerBag(new SessionBagProxy($bag, $data, $usageIndex, null));

        self::assertSame($bag, (new Session($storage))->getBag('foo'));
    }
}
