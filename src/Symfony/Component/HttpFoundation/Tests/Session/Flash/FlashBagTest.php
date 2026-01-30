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
    protected array $array = [];

    private FlashBag $bag;

    protected function setUp(): void
    {
        $this->bag = new FlashBag();
        $this->array = ['notice' => ['A previous flash message']];
        $this->bag->initialize($this->array);
    }

    protected function tearDown(): void
    {
        unset($this->bag);
    }

    public function testInitialize(): void
    {
        $bag = new FlashBag();
        $bag->initialize($this->array);
        $this->assertEquals($this->array, $bag->peekAll());
        $array = ['should' => ['change']];
        $bag->initialize($array);
        $this->assertEquals($array, $bag->peekAll());
    }

    public function testGetStorageKey(): void
    {
        $this->assertEquals('_symfony_flashes', $this->bag->getStorageKey());
        $attributeBag = new FlashBag('test');
        $this->assertEquals('test', $attributeBag->getStorageKey());
    }

    public function testGetSetName(): void
    {
        $this->assertEquals('flashes', $this->bag->getName());
        $this->bag->setName('foo');
        $this->assertEquals('foo', $this->bag->getName());
    }

    public function testPeek(): void
    {
        $this->assertEquals([], $this->bag->peek('non_existing'));
        $this->assertEquals(['default'], $this->bag->peek('not_existing', ['default']));
        $this->assertEquals(['A previous flash message'], $this->bag->peek('notice'));
        $this->assertEquals(['A previous flash message'], $this->bag->peek('notice'));
    }

    public function testAdd(): void
    {
        $tab = ['bar' => 'baz'];
        $this->bag->add('string_message', 'lorem');
        $this->bag->add('object_message', new \stdClass());
        $this->bag->add('array_message', $tab);

        $this->assertEquals(['lorem'], $this->bag->get('string_message'));
        $this->assertEquals([new \stdClass()], $this->bag->get('object_message'));
        $this->assertEquals([$tab], $this->bag->get('array_message'));
    }

    public function testGet(): void
    {
        $this->assertEquals([], $this->bag->get('non_existing'));
        $this->assertEquals(['default'], $this->bag->get('not_existing', ['default']));
        $this->assertEquals(['A previous flash message'], $this->bag->get('notice'));
        $this->assertEquals([], $this->bag->get('notice'));
    }

    public function testAll(): void
    {
        $this->bag->set('notice', 'Foo');
        $this->bag->set('error', 'Bar');
        $this->assertEquals([
            'notice' => ['Foo'],
            'error' => ['Bar'], ], $this->bag->all()
        );

        $this->assertEquals([], $this->bag->all());
    }

    public function testSet(): void
    {
        $this->bag->set('notice', 'Foo');
        $this->bag->set('notice', 'Bar');
        $this->assertEquals(['Bar'], $this->bag->peek('notice'));
    }

    public function testHas(): void
    {
        $this->assertFalse($this->bag->has('nothing'));
        $this->assertTrue($this->bag->has('notice'));
    }

    public function testKeys(): void
    {
        $this->assertEquals(['notice'], $this->bag->keys());
    }

    public function testSetAll(): void
    {
        $this->bag->add('one_flash', 'Foo');
        $this->bag->add('another_flash', 'Bar');
        $this->assertTrue($this->bag->has('one_flash'));
        $this->assertTrue($this->bag->has('another_flash'));
        $this->bag->setAll(['unique_flash' => 'FooBar']);
        $this->assertFalse($this->bag->has('one_flash'));
        $this->assertFalse($this->bag->has('another_flash'));
        $this->assertSame(['unique_flash' => 'FooBar'], $this->bag->all());
        $this->assertSame([], $this->bag->all());
    }

    public function testPeekAll(): void
    {
        $this->bag->set('notice', 'Foo');
        $this->bag->set('error', 'Bar');
        $this->assertEquals([
            'notice' => ['Foo'],
            'error' => ['Bar'],
        ], $this->bag->peekAll()
        );
        $this->assertTrue($this->bag->has('notice'));
        $this->assertTrue($this->bag->has('error'));
        $this->assertEquals([
            'notice' => ['Foo'],
            'error' => ['Bar'],
        ], $this->bag->peekAll()
        );
    }
}
