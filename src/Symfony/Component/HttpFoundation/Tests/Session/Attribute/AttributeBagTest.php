<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Session\Attribute;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;

/**
 * Tests AttributeBag.
 *
 * @author Drak <drak@zikula.org>
 */
class AttributeBagTest extends TestCase
{
    private $array = [];

    /**
     * @var AttributeBag
     */
    private $bag;

    protected function setUp(): void
    {
        $this->array = [
            'hello' => 'world',
            'always' => 'be happy',
            'user.login' => 'drak',
            'csrf.token' => [
                'a' => '1234',
                'b' => '4321',
            ],
            'category' => [
                'fishing' => [
                    'first' => 'cod',
                    'second' => 'sole',
                ],
            ],
        ];
        $this->bag = new AttributeBag('_sf');
        $this->bag->initialize($this->array);
    }

    protected function tearDown(): void
    {
        $this->bag = null;
        $this->array = [];
    }

    public function testInitialize()
    {
        $bag = new AttributeBag();
        $bag->initialize($this->array);
        self::assertEquals($this->array, $bag->all());
        $array = ['should' => 'change'];
        $bag->initialize($array);
        self::assertEquals($array, $bag->all());
    }

    public function testGetStorageKey()
    {
        self::assertEquals('_sf', $this->bag->getStorageKey());
        $attributeBag = new AttributeBag('test');
        self::assertEquals('test', $attributeBag->getStorageKey());
    }

    public function testGetSetName()
    {
        self::assertEquals('attributes', $this->bag->getName());
        $this->bag->setName('foo');
        self::assertEquals('foo', $this->bag->getName());
    }

    /**
     * @dataProvider attributesProvider
     */
    public function testHas($key, $value, $exists)
    {
        self::assertEquals($exists, $this->bag->has($key));
    }

    /**
     * @dataProvider attributesProvider
     */
    public function testGet($key, $value, $expected)
    {
        self::assertEquals($value, $this->bag->get($key));
    }

    public function testGetDefaults()
    {
        self::assertNull($this->bag->get('user2.login'));
        self::assertEquals('default', $this->bag->get('user2.login', 'default'));
    }

    /**
     * @dataProvider attributesProvider
     */
    public function testSet($key, $value, $expected)
    {
        $this->bag->set($key, $value);
        self::assertEquals($value, $this->bag->get($key));
    }

    public function testAll()
    {
        self::assertEquals($this->array, $this->bag->all());

        $this->bag->set('hello', 'fabien');
        $array = $this->array;
        $array['hello'] = 'fabien';
        self::assertEquals($array, $this->bag->all());
    }

    public function testReplace()
    {
        $array = [];
        $array['name'] = 'jack';
        $array['foo.bar'] = 'beep';
        $this->bag->replace($array);
        self::assertEquals($array, $this->bag->all());
        self::assertNull($this->bag->get('hello'));
        self::assertNull($this->bag->get('always'));
        self::assertNull($this->bag->get('user.login'));
    }

    public function testRemove()
    {
        self::assertEquals('world', $this->bag->get('hello'));
        $this->bag->remove('hello');
        self::assertNull($this->bag->get('hello'));

        self::assertEquals('be happy', $this->bag->get('always'));
        $this->bag->remove('always');
        self::assertNull($this->bag->get('always'));

        self::assertEquals('drak', $this->bag->get('user.login'));
        $this->bag->remove('user.login');
        self::assertNull($this->bag->get('user.login'));
    }

    public function testClear()
    {
        $this->bag->clear();
        self::assertEquals([], $this->bag->all());
    }

    public function attributesProvider()
    {
        return [
            ['hello', 'world', true],
            ['always', 'be happy', true],
            ['user.login', 'drak', true],
            ['csrf.token', ['a' => '1234', 'b' => '4321'], true],
            ['category', ['fishing' => ['first' => 'cod', 'second' => 'sole']], true],
            ['user2.login', null, false],
            ['never', null, false],
            ['bye', null, false],
            ['bye/for/now', null, false],
        ];
    }

    public function testGetIterator()
    {
        $i = 0;
        foreach ($this->bag as $key => $val) {
            self::assertEquals($this->array[$key], $val);
            ++$i;
        }

        self::assertEquals(\count($this->array), $i);
    }

    public function testCount()
    {
        self::assertCount(\count($this->array), $this->bag);
    }
}
