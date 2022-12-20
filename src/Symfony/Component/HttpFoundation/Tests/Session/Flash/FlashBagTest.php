<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Session\Flash;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;

/**
 * FlashBagTest.
 *
 * @author Drak <drak@zikula.org>
 */
class FlashBagTest extends TestCase
{
    /**
     * @var \Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface
     */
    private $bag;

    protected $array = [];

    protected function setUp(): void
    {
        self::setUp();
        $this->bag = new FlashBag();
        $this->array = ['notice' => ['A previous flash message']];
        $this->bag->initialize($this->array);
    }

    protected function tearDown(): void
    {
        $this->bag = null;
        self::tearDown();
    }

    public function testInitialize()
    {
        $bag = new FlashBag();
        $bag->initialize($this->array);
        self::assertEquals($this->array, $bag->peekAll());
        $array = ['should' => ['change']];
        $bag->initialize($array);
        self::assertEquals($array, $bag->peekAll());
    }

    public function testGetStorageKey()
    {
        self::assertEquals('_symfony_flashes', $this->bag->getStorageKey());
        $attributeBag = new FlashBag('test');
        self::assertEquals('test', $attributeBag->getStorageKey());
    }

    public function testGetSetName()
    {
        self::assertEquals('flashes', $this->bag->getName());
        $this->bag->setName('foo');
        self::assertEquals('foo', $this->bag->getName());
    }

    public function testPeek()
    {
        self::assertEquals([], $this->bag->peek('non_existing'));
        self::assertEquals(['default'], $this->bag->peek('not_existing', ['default']));
        self::assertEquals(['A previous flash message'], $this->bag->peek('notice'));
        self::assertEquals(['A previous flash message'], $this->bag->peek('notice'));
    }

    public function testAdd()
    {
        $tab = ['bar' => 'baz'];
        $this->bag->add('string_message', 'lorem');
        $this->bag->add('object_message', new \stdClass());
        $this->bag->add('array_message', $tab);

        self::assertEquals(['lorem'], $this->bag->get('string_message'));
        self::assertEquals([new \stdClass()], $this->bag->get('object_message'));
        self::assertEquals([$tab], $this->bag->get('array_message'));
    }

    public function testGet()
    {
        self::assertEquals([], $this->bag->get('non_existing'));
        self::assertEquals(['default'], $this->bag->get('not_existing', ['default']));
        self::assertEquals(['A previous flash message'], $this->bag->get('notice'));
        self::assertEquals([], $this->bag->get('notice'));
    }

    public function testAll()
    {
        $this->bag->set('notice', 'Foo');
        $this->bag->set('error', 'Bar');
        self::assertEquals([
            'notice' => ['Foo'],
            'error' => ['Bar'], ], $this->bag->all());

        self::assertEquals([], $this->bag->all());
    }

    public function testSet()
    {
        $this->bag->set('notice', 'Foo');
        $this->bag->set('notice', 'Bar');
        self::assertEquals(['Bar'], $this->bag->peek('notice'));
    }

    public function testHas()
    {
        self::assertFalse($this->bag->has('nothing'));
        self::assertTrue($this->bag->has('notice'));
    }

    public function testKeys()
    {
        self::assertEquals(['notice'], $this->bag->keys());
    }

    public function testSetAll()
    {
        $this->bag->add('one_flash', 'Foo');
        $this->bag->add('another_flash', 'Bar');
        self::assertTrue($this->bag->has('one_flash'));
        self::assertTrue($this->bag->has('another_flash'));
        $this->bag->setAll(['unique_flash' => 'FooBar']);
        self::assertFalse($this->bag->has('one_flash'));
        self::assertFalse($this->bag->has('another_flash'));
        self::assertSame(['unique_flash' => 'FooBar'], $this->bag->all());
        self::assertSame([], $this->bag->all());
    }

    public function testPeekAll()
    {
        $this->bag->set('notice', 'Foo');
        $this->bag->set('error', 'Bar');
        self::assertEquals([
            'notice' => ['Foo'],
            'error' => ['Bar'],
            ], $this->bag->peekAll());
        self::assertTrue($this->bag->has('notice'));
        self::assertTrue($this->bag->has('error'));
        self::assertEquals([
            'notice' => ['Foo'],
            'error' => ['Bar'],
            ], $this->bag->peekAll());
    }
}
