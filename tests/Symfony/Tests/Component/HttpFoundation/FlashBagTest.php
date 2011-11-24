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

use Symfony\Component\HttpFoundation\FlashBag;
use Symfony\Component\HttpFoundation\FlashBagInterface;

/**
 * FlashBagTest
 *
 * @author Drak <drak@zikula.org>
 */
class FlashBagTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Symfony\Component\HttpFoundation\FlashBagInterface
     */
    private $flashBag;
    
    public function setUp()
    {
        parent::setUp();
        $this->flashBag = new FlashBag();
        $flashes = array(FlashBagInterface::NOTICE => array('A previous flash message'));
        $this->flashBag->initialize($flashes);
    }
    
    public function tearDown()
    {
        $this->flashBag = null;
        parent::tearDown();
    }
    
    public function testInitialize()
    {
        $data = array();
        $this->flashBag->initialize($data);
        $this->flashBag->initialize($data);
    }

    /**
     * @todo Implement testAdd().
     */
    public function testAdd()
    {
        $this->flashBag->add('Something new', FlashBag::STATUS);
        $this->flashBag->add('Smile, it might work next time', FlashBag::ERROR);
        $this->assertEquals(array('A previous flash message', 'Something new'), $this->flashBag->get(FlashBag::STATUS));
        $this->assertEquals(array('Smile, it might work next time'), $this->flashBag->get(FlashBag::ERROR));
    }

    public function testGet()
    {
        $this->assertEquals(array('A previous flash message'), $this->flashBag->get(FlashBag::STATUS));
        $this->assertEquals(array('A previous flash message'), $this->flashBag->get(FlashBag::STATUS, true));
        $this->assertFalse($this->flashBag->has(FlashBag::STATUS));
    }
    
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetException()
    {
        $bang = $this->flashBag->get('bang');
    }

    public function testSet()
    {
        $this->flashBag->set(FlashBag::STATUS, array('Foo', 'Bar'));
        $this->assertEquals(array('Foo', 'Bar'), $this->flashBag->get(FlashBag::STATUS));
    }

    public function testHas()
    {
        $this->assertFalse($this->flashBag->has('nothing'));
        $this->assertTrue($this->flashBag->has(FlashBag::STATUS));
    }

    /**
     * @todo Implement testGetTypes().
     */
    public function testGetTypes()
    {
        $this->assertEquals(array(FlashBag::STATUS), $this->flashBag->getTypes());
    }

    public function testAll()
    {
        $this->flashBag->set(FlashBag::STATUS, array('Foo'));
        $this->flashBag->set(FlashBag::ERROR, array('Bar'));
        $this->assertEquals(array(
            FlashBag::STATUS => array('Foo'), 
            FlashBag::ERROR => array('Bar')), 
                $this->flashBag->all()
                );
        $this->assertTrue($this->flashBag->has(FlashBag::STATUS));
        $this->assertTrue($this->flashBag->has(FlashBag::ERROR));
        $this->assertEquals(array(
            FlashBag::STATUS => array('Foo'), 
            FlashBag::ERROR => array('Bar')),  
                $this->flashBag->all(true)
                );
        $this->assertFalse($this->flashBag->has(FlashBag::STATUS));
        $this->assertFalse($this->flashBag->has(FlashBag::ERROR));
        $this->assertEquals(array(), $this->flashBag->all());
    }

    public function testClear()
    {
        $this->assertTrue($this->flashBag->has(FlashBag::STATUS));
        $this->flashBag->clear(FlashBag::STATUS);
        $this->assertFalse($this->flashBag->has(FlashBag::STATUS));
    }

    public function testClearAll()
    {
        $this->assertTrue($this->flashBag->has(FlashBag::STATUS));
        $this->flashBag->add('Smile, it might work next time', FlashBag::ERROR);
        $this->assertTrue($this->flashBag->has(FlashBag::ERROR));
        $this->flashBag->clearAll();
        $this->assertFalse($this->flashBag->has(FlashBag::STATUS));
        $this->assertFalse($this->flashBag->has(FlashBag::ERROR));
    }
}