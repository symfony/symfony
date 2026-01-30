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
use Symfony\Component\HttpFoundation\Session\Flash\AutoExpireFlashBag as FlashBag;

/**
 * AutoExpireFlashBagTest.
 *
 * @author Drak <drak@zikula.org>
 */
class AutoExpireFlashBagTest extends TestCase
{
    protected array $array = [];

    private FlashBag $bag;

    protected function setUp(): void
    {
        $this->bag = new FlashBag();
        $this->array = ['new' => ['notice' => ['A previous flash message']]];
        $this->bag->initialize($this->array);
    }

    protected function tearDown(): void
    {
        unset($this->bag);
    }

    public function testInitialize(): void
    {
        $bag = new FlashBag();
        $array = ['new' => ['notice' => ['A previous flash message']]];
        $bag->initialize($array);
        $this->assertEquals(['A previous flash message'], $bag->peek('notice'));
        $array = ['new' => [
            'notice' => ['Something else'],
            'error' => ['a'],
        ]];
        $bag->initialize($array);
        $this->assertEquals(['Something else'], $bag->peek('notice'));
        $this->assertEquals(['a'], $bag->peek('error'));
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
        $this->assertEquals(['default'], $this->bag->peek('non_existing', ['default']));
        $this->assertEquals(['A previous flash message'], $this->bag->peek('notice'));
        $this->assertEquals(['A previous flash message'], $this->bag->peek('notice'));
    }

    public function testSet(): void
    {
        $this->bag->set('notice', 'Foo');
        $this->assertEquals(['A previous flash message'], $this->bag->peek('notice'));
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

    public function testPeekAll(): void
    {
        $array = [
            'new' => [
                'notice' => 'Foo',
                'error' => 'Bar',
            ],
        ];

        $this->bag->initialize($array);
        $this->assertEquals([
            'notice' => 'Foo',
            'error' => 'Bar',
        ], $this->bag->peekAll()
        );

        $this->assertEquals([
            'notice' => 'Foo',
            'error' => 'Bar',
        ], $this->bag->peekAll()
        );
    }

    public function testGet(): void
    {
        $this->assertEquals([], $this->bag->get('non_existing'));
        $this->assertEquals(['default'], $this->bag->get('non_existing', ['default']));
        $this->assertEquals(['A previous flash message'], $this->bag->get('notice'));
        $this->assertEquals([], $this->bag->get('notice'));
    }

    public function testSetAll(): void
    {
        $this->bag->setAll(['a' => 'first', 'b' => 'second']);
        $this->assertFalse($this->bag->has('a'));
        $this->assertFalse($this->bag->has('b'));
    }

    public function testAll(): void
    {
        $this->bag->set('notice', 'Foo');
        $this->bag->set('error', 'Bar');
        $this->assertEquals([
            'notice' => ['A previous flash message'],
        ], $this->bag->all()
        );

        $this->assertEquals([], $this->bag->all());
    }

    public function testClear(): void
    {
        $this->assertEquals(['notice' => ['A previous flash message']], $this->bag->clear());
    }

    public function testDoNotRemoveTheNewFlashesWhenDisplayingTheExistingOnes(): void
    {
        $this->bag->add('success', 'Something');
        $this->bag->all();

        $this->assertEquals(['new' => ['success' => ['Something']], 'display' => []], $this->array);
    }
}
