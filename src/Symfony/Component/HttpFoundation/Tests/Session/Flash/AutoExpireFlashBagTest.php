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
    /**
     * @var \Symfony\Component\HttpFoundation\Session\Flash\AutoExpireFlashBag
     */
    private $bag;

    protected $array = [];

    protected function setUp(): void
    {
        self::setUp();
        $this->bag = new FlashBag();
        $this->array = ['new' => ['notice' => ['A previous flash message']]];
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
        $array = ['new' => ['notice' => ['A previous flash message']]];
        $bag->initialize($array);
        self::assertEquals(['A previous flash message'], $bag->peek('notice'));
        $array = ['new' => [
                'notice' => ['Something else'],
                'error' => ['a'],
            ]];
        $bag->initialize($array);
        self::assertEquals(['Something else'], $bag->peek('notice'));
        self::assertEquals(['a'], $bag->peek('error'));
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
        self::assertEquals(['default'], $this->bag->peek('non_existing', ['default']));
        self::assertEquals(['A previous flash message'], $this->bag->peek('notice'));
        self::assertEquals(['A previous flash message'], $this->bag->peek('notice'));
    }

    public function testSet()
    {
        $this->bag->set('notice', 'Foo');
        self::assertEquals(['A previous flash message'], $this->bag->peek('notice'));
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

    public function testPeekAll()
    {
        $array = [
            'new' => [
                'notice' => 'Foo',
                'error' => 'Bar',
            ],
        ];

        $this->bag->initialize($array);
        self::assertEquals([
            'notice' => 'Foo',
            'error' => 'Bar',
            ], $this->bag->peekAll());

        self::assertEquals([
            'notice' => 'Foo',
            'error' => 'Bar',
            ], $this->bag->peekAll());
    }

    public function testGet()
    {
        self::assertEquals([], $this->bag->get('non_existing'));
        self::assertEquals(['default'], $this->bag->get('non_existing', ['default']));
        self::assertEquals(['A previous flash message'], $this->bag->get('notice'));
        self::assertEquals([], $this->bag->get('notice'));
    }

    public function testSetAll()
    {
        $this->bag->setAll(['a' => 'first', 'b' => 'second']);
        self::assertFalse($this->bag->has('a'));
        self::assertFalse($this->bag->has('b'));
    }

    public function testAll()
    {
        $this->bag->set('notice', 'Foo');
        $this->bag->set('error', 'Bar');
        self::assertEquals([
            'notice' => ['A previous flash message'],
            ], $this->bag->all());

        self::assertEquals([], $this->bag->all());
    }

    public function testClear()
    {
        self::assertEquals(['notice' => ['A previous flash message']], $this->bag->clear());
    }

    public function testDoNotRemoveTheNewFlashesWhenDisplayingTheExistingOnes()
    {
        $this->bag->add('success', 'Something');
        $this->bag->all();

        self::assertEquals(['new' => ['success' => ['Something']], 'display' => []], $this->array);
    }
}
