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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;

/**
 * Tests AttributeBag.
 *
 * @author Drak <drak@zikula.org>
 */
class AttributeBagTest extends TestCase
{
    private array $array = [];

    private ?AttributeBag $bag = null;

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
        $this->assertEquals($this->array, $bag->all());
        $array = ['should' => 'change'];
        $bag->initialize($array);
        $this->assertEquals($array, $bag->all());
    }

    public function testGetStorageKey()
    {
        $this->assertEquals('_sf', $this->bag->getStorageKey());
        $attributeBag = new AttributeBag('test');
        $this->assertEquals('test', $attributeBag->getStorageKey());
    }

    public function testGetSetName()
    {
        $this->assertEquals('attributes', $this->bag->getName());
        $this->bag->setName('foo');
        $this->assertEquals('foo', $this->bag->getName());
    }

    #[DataProvider('attributesProvider')]
    public function testHas($key, $value, $exists)
    {
        $this->assertEquals($exists, $this->bag->has($key));
    }

    #[DataProvider('attributesProvider')]
    public function testGet($key, $value, $expected)
    {
        $this->assertEquals($value, $this->bag->get($key));
    }

    public function testGetDefaults()
    {
        $this->assertNull($this->bag->get('user2.login'));
        $this->assertEquals('default', $this->bag->get('user2.login', 'default'));
    }

    #[DataProvider('attributesProvider')]
    public function testSet($key, $value, $expected)
    {
        $this->bag->set($key, $value);
        $this->assertEquals($value, $this->bag->get($key));
    }

    public function testAll()
    {
        $this->assertEquals($this->array, $this->bag->all());

        $this->bag->set('hello', 'fabien');
        $array = $this->array;
        $array['hello'] = 'fabien';
        $this->assertEquals($array, $this->bag->all());
    }

    public function testReplace()
    {
        $array = [];
        $array['name'] = 'jack';
        $array['foo.bar'] = 'beep';
        $this->bag->replace($array);
        $this->assertEquals($array, $this->bag->all());
        $this->assertNull($this->bag->get('hello'));
        $this->assertNull($this->bag->get('always'));
        $this->assertNull($this->bag->get('user.login'));
    }

    public function testRemove()
    {
        $this->assertEquals('world', $this->bag->get('hello'));
        $this->bag->remove('hello');
        $this->assertNull($this->bag->get('hello'));

        $this->assertEquals('be happy', $this->bag->get('always'));
        $this->bag->remove('always');
        $this->assertNull($this->bag->get('always'));

        $this->assertEquals('drak', $this->bag->get('user.login'));
        $this->bag->remove('user.login');
        $this->assertNull($this->bag->get('user.login'));
    }

    public function testClear()
    {
        $this->bag->clear();
        $this->assertEquals([], $this->bag->all());
    }

    public static function attributesProvider()
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
            $this->assertEquals($this->array[$key], $val);
            ++$i;
        }

        $this->assertEquals(\count($this->array), $i);
    }

    public function testCount()
    {
        $this->assertCount(\count($this->array), $this->bag);
    }

    public function testNumericKeysAreExposedAsStrings()
    {
        $bag = new AttributeBag();
        $bag->set('123', 'foo');
        $bag->set('bar', 'baz');

        $keys = [];
        foreach ($bag as $key => $value) {
            $keys[] = $key;
        }

        $this->assertSame(['123', 'bar'], $keys);
    }

    public function testGetDoesNotIsolateValuesByDefault()
    {
        $object = new \stdClass();
        $array = ['obj' => $object];
        $bag = new AttributeBag();
        $bag->initialize($array);

        $this->assertSame($object, $bag->get('obj'));
    }

    public function testIsolatedGetReturnsTheSameCopyEveryTime()
    {
        $object = new \stdClass();
        $object->foo = 'bar';
        $array = ['obj' => $object, 'list' => [$object], 'scalars' => ['a' => 1], 'scalar' => 'value'];
        $bag = new AttributeBag('_sf2_attributes', true);
        $bag->initialize($array);

        $copy = $bag->get('obj');
        $this->assertEquals($object, $copy);
        $this->assertNotSame($object, $copy);
        $this->assertSame($copy, $bag->get('obj'));

        $copy->foo = 'baz';
        $bag->get('list')[0]->foo = 'baz';

        $this->assertSame('bar', $array['obj']->foo);
        $this->assertSame('bar', $array['list'][0]->foo);
        $this->assertSame('baz', $bag->get('obj')->foo);
        $this->assertSame('baz', $bag->get('list')[0]->foo);
        $this->assertSame(['a' => 1], $bag->get('scalars'));
        $this->assertSame('value', $bag->get('scalar'));
    }

    public function testIsolatedSetSavesTheValueAsPassed()
    {
        $array = [];
        $bag = new AttributeBag('_sf2_attributes', true);
        $bag->initialize($array);

        $object = new \stdClass();
        $object->foo = 'bar';
        $bag->set('obj', $object);
        $bag->set('list', [$object]);
        $object->foo = 'baz';

        $this->assertSame($object, $bag->get('obj'));
        $this->assertSame([$object], $bag->get('list'));
        $this->assertSame('bar', $array['obj']->foo);
        $this->assertSame('bar', $array['list'][0]->foo);

        $bag->set('obj', $object);

        $this->assertSame('baz', $array['obj']->foo);
        $this->assertNotSame($object, $array['obj']);
    }

    public function testIsolatedAllRemoveAndClearReturnTheIsolatedValues()
    {
        $object = new \stdClass();
        $array = ['obj' => $object, 'scalar' => 'value'];
        $bag = new AttributeBag('_sf2_attributes', true);
        $bag->initialize($array);

        $copy = $bag->get('obj');
        $this->assertNotSame($object, $copy);
        $this->assertSame(['obj' => $copy, 'scalar' => 'value'], $bag->all());
        $this->assertSame(['obj' => $copy, 'scalar' => 'value'], iterator_to_array($bag));
        $this->assertSame($copy, $bag->remove('obj'));
        $this->assertSame(['scalar' => 'value'], $array);

        $bag->set('obj', $object);
        $this->assertSame(['scalar' => 'value', 'obj' => $object], $bag->clear());
        $this->assertSame([], $array);
    }

    public function testIsolatedAllCopiesValuesNotReadYet()
    {
        $object = new \stdClass();
        $array = ['obj' => $object];
        $bag = new AttributeBag('_sf2_attributes', true);
        $bag->initialize($array);

        $all = $bag->all();
        $this->assertNotSame($object, $all['obj']);
        $this->assertSame($all['obj'], $bag->get('obj'));
    }

    public function testIsolatedReplaceAndInitializeDropPreviousValues()
    {
        $array = [];
        $bag = new AttributeBag('_sf2_attributes', true);
        $bag->initialize($array);

        $object = new \stdClass();
        $bag->set('obj', $object);
        $bag->replace(['other' => $object]);
        $this->assertNull($bag->get('obj'));
        $this->assertSame($object, $bag->get('other'));

        $newArray = ['other' => $object];
        $bag->initialize($newArray);
        $this->assertNotSame($object, $bag->get('other'));
    }
}
