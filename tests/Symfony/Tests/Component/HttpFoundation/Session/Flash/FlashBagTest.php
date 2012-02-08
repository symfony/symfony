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
        $this->array = array(FlashBag::NOTICE => 'A previous flash message');
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
        $this->assertEquals($this->array, $bag->all());
        $array = array('should' => array('change'));
        $bag->initialize($array);
        $this->assertEquals($array, $bag->all());
    }

    public function testGet()
    {
        $this->assertEquals('A previous flash message', $this->bag->get(FlashBag::NOTICE));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetException()
    {
        $this->bag->get('non_existing_type');
    }

    public function testPop()
    {
        $this->assertEquals('A previous flash message', $this->bag->pop(FlashBag::NOTICE));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testPopException()
    {
        $this->assertEquals('A previous flash message', $this->bag->pop(FlashBag::NOTICE));
        $this->bag->pop(FlashBag::NOTICE);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testPopExceptionNotExisting()
    {
        $this->bag->pop('non_existing_type');
    }

    public function testPopAll()
    {
        $this->bag->set(FlashBag::NOTICE, 'Foo');
        $this->bag->set(FlashBag::ERROR, 'Bar');
        $this->assertEquals(array(
            FlashBag::NOTICE => 'Foo',
            FlashBag::ERROR => 'Bar'), $this->bag->popAll()
        );

        $this->assertEquals(array(), $this->bag->popAll());
    }

    public function testset()
    {
        $this->bag->set(FlashBag::NOTICE, 'Foo');
        $this->bag->set(FlashBag::NOTICE, 'Bar');
        $this->assertEquals('Bar', $this->bag->get(FlashBag::NOTICE));
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
        $this->bag->set(FlashBag::NOTICE, 'Foo');
        $this->bag->set(FlashBag::ERROR, 'Bar');
        $this->assertEquals(array(
            FlashBag::NOTICE => 'Foo',
            FlashBag::ERROR => 'Bar',
            ), $this->bag->all()
        );
        $this->assertTrue($this->bag->has(FlashBag::NOTICE));
        $this->assertTrue($this->bag->has(FlashBag::ERROR));
        $this->assertEquals(array(
            FlashBag::NOTICE => 'Foo',
            FlashBag::ERROR => 'Bar',
            ), $this->bag->all()
        );
    }
}
