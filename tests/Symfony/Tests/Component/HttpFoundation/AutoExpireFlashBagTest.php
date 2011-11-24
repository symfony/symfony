<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpFoundation;

use Symfony\Component\HttpFoundation\AutoExpireFlashBag as FlashBag;
use Symfony\Component\HttpFoundation\FlashBagInterface;

/**
 * AutoExpireFlashBagTest
 *
 * @author Drak <drak@zikula.org>
 */
class AutoExpireFlashBagTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Symfony\Component\HttpFoundation\FlashBagInterface
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
        $this->array = array('new' => array(FlashBag::NOTICE => array('A previous flash message')));
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
        $array = array('new' => array(FlashBag::NOTICE => array('A previous flash message')));
        $bag->initialize($array);
        $this->assertEquals(array('A previous flash message'), $bag->get(FlashBag::NOTICE));
        $array = array('new' => array(
                FlashBag::NOTICE => array('Something else'),
                FlashBag::ERROR => array('a', 'b'),
            ));
        $bag->initialize($array);
        $this->assertEquals(array('Something else'), $bag->get(FlashBag::NOTICE));
        $this->assertEquals(array('a', 'b'), $bag->get(FlashBag::ERROR));
    }

    public function testAdd()
    {
        $this->bag->add('Something new', FlashBag::NOTICE);
        $this->bag->add('Smile, it might work next time', FlashBag::ERROR);
        $this->assertEquals(array('A previous flash message'), $this->bag->get(FlashBag::NOTICE));
        $this->assertEquals(array(), $this->bag->get(FlashBag::ERROR));
    }

    public function testGet()
    {
        $this->assertEquals(array('A previous flash message'), $this->bag->get(FlashBag::NOTICE));
        $this->assertEquals(array(), $this->bag->get('non_existing_type'));
    }

    public function testSet()
    {
        $this->bag->set(FlashBag::NOTICE, array('Foo', 'Bar'));
        $this->assertNotEquals(array('Foo', 'Bar'), $this->bag->get(FlashBag::NOTICE));
    }

    public function testHas()
    {
        $this->assertFalse($this->bag->has('nothing'));
        $this->assertTrue($this->bag->has(FlashBag::NOTICE));
    }

    public function testKeys()
    {
        $this->assertEquals(array(FlashBag::NOTICE), $this->bag->keys());
    }

    public function testAll()
    {
        $array = array(
            'new' => array(
                FlashBag::NOTICE => array('Foo'),
                FlashBag::ERROR => array('Bar'),
            ),
        );

        $this->bag->initialize($array);
        $this->assertEquals(array(
            FlashBag::NOTICE => array('Foo'),
            FlashBag::ERROR => array('Bar'),
            ), $this->bag->all()
        );
    }

    public function testPop()
    {
        $this->assertEquals(array('A previous flash message'), $this->bag->pop(FlashBag::NOTICE));
        $this->assertEquals(array(), $this->bag->pop(FlashBag::NOTICE));
        $this->assertEquals(array(), $this->bag->pop('non_existing_type'));
    }

    public function testPopAll()
    {
        $this->bag->set(FlashBag::NOTICE, array('Foo'));
        $this->bag->set(FlashBag::ERROR, array('Bar'));
        $this->assertEquals(array(
            FlashBag::NOTICE => array('A previous flash message'),
            ), $this->bag->popAll()
        );

        $this->assertEquals(array(), $this->bag->popAll());
    }

    public function testClear()
    {
        $this->assertTrue($this->bag->has(FlashBag::NOTICE));
        $this->assertEquals(array('A previous flash message'), $this->bag->clear(FlashBag::NOTICE));
        $this->assertEquals(array(), $this->bag->clear(FlashBag::NOTICE));
        $this->assertFalse($this->bag->has(FlashBag::NOTICE));
    }

    public function testClearAll()
    {
        $this->assertTrue($this->bag->has(FlashBag::NOTICE));
        $this->bag->add('Smile, it might work next time', FlashBag::ERROR);
        $this->assertFalse($this->bag->has(FlashBag::ERROR));
        $this->assertEquals(array(
            FlashBag::NOTICE => array('A previous flash message'),
            ), $this->bag->clearAll()
        );
        $this->assertEquals(array(), $this->bag->clearAll());
        $this->assertFalse($this->bag->has(FlashBag::NOTICE));
        $this->assertFalse($this->bag->has(FlashBag::ERROR));
    }

}
