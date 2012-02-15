<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpFoundation\Session\Flash;

use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

/**
 * FlashBagTest
 *
 * @author Drak <drak@zikula.org>
 */
class FlashBagTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Symfony\Component\HttpFoundation\SessionFlash\FlashBagInterface
     */
    private $bag;

    /**
     * @var array
     */
    protected $array = array();

    public function setUp()
    {
        parent::setUp();
        $this->bag = new FlashBag();
        $this->array = array('notice' => 'A previous flash message');
        $this->bag->initialize($this->array);
    }

    public function tearDown()
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
        $this->assertNull($this->bag->peek('non_existing'));
        $this->assertEquals('default', $this->bag->peek('not_existing', 'default'));
        $this->assertEquals('A previous flash message', $this->bag->peek('notice'));
        $this->assertEquals('A previous flash message', $this->bag->peek('notice'));
    }

    public function testGet()
    {
        $this->assertNull($this->bag->get('non_existing'));
        $this->assertEquals('default', $this->bag->get('not_existing', 'default'));
        $this->assertEquals('A previous flash message', $this->bag->get('notice'));
        $this->assertNull($this->bag->get('notice'));
    }

    public function testAll()
    {
        $this->bag->set('notice', 'Foo');
        $this->bag->set('error', 'Bar');
        $this->assertEquals(array(
            'notice' => 'Foo',
            'error' => 'Bar'), $this->bag->all()
        );

        $this->assertEquals(array(), $this->bag->all());
    }

    public function testSet()
    {
        $this->bag->set('notice', 'Foo');
        $this->bag->set('notice', 'Bar');
        $this->assertEquals('Bar', $this->bag->peek('notice'));
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

    public function testPeekAll()
    {
        $this->bag->set('notice', 'Foo');
        $this->bag->set('error', 'Bar');
        $this->assertEquals(array(
            'notice' => 'Foo',
            'error' => 'Bar',
            ), $this->bag->peekAll()
        );
        $this->assertTrue($this->bag->has('notice'));
        $this->assertTrue($this->bag->has('error'));
        $this->assertEquals(array(
            'notice' => 'Foo',
            'error' => 'Bar',
            ), $this->bag->peekAll()
        );
    }
}
