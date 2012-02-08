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

use Symfony\Component\HttpFoundation\Session\Flash\AutoExpireFlashBag as FlashBag;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

/**
 * AutoExpireFlashBagTest
 *
 * @author Drak <drak@zikula.org>
 */
class AutoExpireFlashBagTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface
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
        $this->array = array('new' => array(FlashBag::NOTICE => 'A previous flash message'));
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
        $array = array('new' => array(FlashBag::NOTICE => 'A previous flash message'));
        $bag->initialize($array);
        $this->assertEquals('A previous flash message', $bag->get(FlashBag::NOTICE));
        $array = array('new' => array(
                FlashBag::NOTICE => 'Something else',
                FlashBag::ERROR => 'a',
            ));
        $bag->initialize($array);
        $this->assertEquals('Something else', $bag->get(FlashBag::NOTICE));
        $this->assertEquals('a', $bag->get(FlashBag::ERROR));
    }

    public function testGet()
    {
        $this->assertEquals('A previous flash message', $this->bag->get(FlashBag::NOTICE));
        $this->assertEquals('A previous flash message', $this->bag->get(FlashBag::NOTICE));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetException()
    {
        $this->bag->get('non_existing_type');
    }

    public function testSet()
    {
        $this->bag->set(FlashBag::NOTICE, 'Foo');
        $this->assertNotEquals('Foo', $this->bag->get(FlashBag::NOTICE));
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
                FlashBag::NOTICE => 'Foo',
                FlashBag::ERROR => 'Bar',
            ),
        );

        $this->bag->initialize($array);
        $this->assertEquals(array(
            FlashBag::NOTICE => 'Foo',
            FlashBag::ERROR => 'Bar',
            ), $this->bag->all()
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testPop()
    {
        $this->assertEquals('A previous flash message', $this->bag->pop(FlashBag::NOTICE));
        $this->bag->pop(FlashBag::NOTICE);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testPopException()
    {
        $this->bag->pop('non_existing_type');
    }

    public function testPopAll()
    {
        $this->bag->set(FlashBag::NOTICE, 'Foo');
        $this->bag->set(FlashBag::ERROR, 'Bar');
        $this->assertEquals(array(
            FlashBag::NOTICE => 'A previous flash message',
            ), $this->bag->popAll()
        );

        $this->assertEquals(array(), $this->bag->popAll());
    }
}
