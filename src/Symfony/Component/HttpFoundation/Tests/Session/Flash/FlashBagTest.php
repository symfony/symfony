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

    protected $array = array();

    protected function setUp()
    {
        parent::setUp();
        $this->bag = new FlashBag();
        $this->array = array('notice' => array('A previous flash message'));
        $this->bag->initialize($this->array);
    }

    protected function tearDown()
    {
        $this->bag = null;
        parent::tearDown();
    }

    public function testInitialize()
    {
        $bag = new FlashBag();
        $bag->initialize($this->array);
        $this->assertEquals($this->array, $bag->peekAll());
        $array = array('should' => array('change'));
        $bag->initialize($array);
        $this->assertEquals($array, $bag->peekAll());
    }

    public function testGetStorageKey()
    {
        $this->assertEquals('_sf2_flashes', $this->bag->getStorageKey());
        $attributeBag = new FlashBag('test');
        $this->assertEquals('test', $attributeBag->getStorageKey());
    }

    public function testGetSetName()
    {
        $this->assertEquals('flashes', $this->bag->getName());
        $this->bag->setName('foo');
        $this->assertEquals('foo', $this->bag->getName());
    }

    public function testPeek()
    {
        $this->assertEquals(array(), $this->bag->peek('non_existing'));
        $this->assertEquals(array('default'), $this->bag->peek('not_existing', array('default')));
        $this->assertEquals(array('A previous flash message'), $this->bag->peek('notice'));
        $this->assertEquals(array('A previous flash message'), $this->bag->peek('notice'));
    }

    public function testAdd()
    {
        $tab = array('bar' => 'baz');
        $this->bag->add('string_message', 'lorem');
        $this->bag->add('object_message', new \stdClass());
        $this->bag->add('array_message', $tab);

        $this->assertEquals(array('lorem'), $this->bag->get('string_message'));
        $this->assertEquals(array(new \stdClass()), $this->bag->get('object_message'));
        $this->assertEquals(array($tab), $this->bag->get('array_message'));
    }

    public function testGet()
    {
        $this->assertEquals(array(), $this->bag->get('non_existing'));
        $this->assertEquals(array('default'), $this->bag->get('not_existing', array('default')));
        $this->assertEquals(array('A previous flash message'), $this->bag->get('notice'));
        $this->assertEquals(array(), $this->bag->get('notice'));
    }

    public function testAll()
    {
        $this->bag->set('notice', 'Foo');
        $this->bag->set('error', 'Bar');
        $this->assertEquals(array(
            'notice' => array('Foo'),
            'error' => array('Bar'), ), $this->bag->all()
        );

        $this->assertEquals(array(), $this->bag->all());
    }

    public function testSet()
    {
        $this->bag->set('notice', 'Foo');
        $this->bag->set('notice', 'Bar');
        $this->assertEquals(array('Bar'), $this->bag->peek('notice'));
    }

    public function testHas()
    {
        $this->assertFalse($this->bag->has('nothing'));
        $this->assertTrue($this->bag->has('notice'));
    }

    public function testKeys()
    {
        $this->assertEquals(array('notice'), $this->bag->keys());
    }

    public function testSetAll()
    {
        $this->bag->add('one_flash', 'Foo');
        $this->bag->add('another_flash', 'Bar');
        $this->assertTrue($this->bag->has('one_flash'));
        $this->assertTrue($this->bag->has('another_flash'));
        $this->bag->setAll(array('unique_flash' => 'FooBar'));
        $this->assertFalse($this->bag->has('one_flash'));
        $this->assertFalse($this->bag->has('another_flash'));
        $this->assertSame(array('unique_flash' => 'FooBar'), $this->bag->all());
        $this->assertSame(array(), $this->bag->all());
    }

    public function testPeekAll()
    {
        $this->bag->set('notice', 'Foo');
        $this->bag->set('error', 'Bar');
        $this->assertEquals(array(
            'notice' => array('Foo'),
            'error' => array('Bar'),
            ), $this->bag->peekAll()
        );
        $this->assertTrue($this->bag->has('notice'));
        $this->assertTrue($this->bag->has('error'));
        $this->assertEquals(array(
            'notice' => array('Foo'),
            'error' => array('Bar'),
            ), $this->bag->peekAll()
        );
    }

    /**
     * @group legacy
     */
    public function testLegacyGetIterator()
    {
        $flashes = array('hello' => 'world', 'beep' => 'boop', 'notice' => 'nope');
        foreach ($flashes as $key => $val) {
            $this->bag->set($key, $val);
        }

        $i = 0;
        foreach ($this->bag as $key => $val) {
            $this->assertEquals(array($flashes[$key]), $val);
            ++$i;
        }

        $this->assertEquals(\count($flashes), $i);
        $this->assertCount(0, $this->bag->all());
    }
}
