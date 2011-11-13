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
        $this->flashBag->initialize(array('status' => array('A previous flash message')));
    }
    
    public function tearDown()
    {
        $this->flashBag = null;
        parent::tearDown();
    }
    
    public function testInitialize()
    {
        $this->flashBag->initialize(array());
        $this->flashBag->initialize(array());
    }

    /**
     * @todo Implement testAdd().
     */
    public function testAdd()
    {
        $this->flashBag->add('status', 'Something new');
        $this->flashBag->add('error', 'Smile, it might work next time');
        $this->assertEquals(array('A previous flash message', 'Something new'), $this->flashBag->get('status'));
        $this->assertEquals(array('Smile, it might work next time'), $this->flashBag->get('error'));
    }

    public function testGet()
    {
        $this->assertEquals(array('A previous flash message'), $this->flashBag->get('status'));
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
        $this->flashBag->set('status', array('Foo', 'Bar'));
        $this->assertEquals(array('Foo', 'Bar'), $this->flashBag->get('status'));
    }

    public function testHas()
    {
        $this->assertFalse($this->flashBag->has('nothing'));
        $this->assertTrue($this->flashBag->has('status'));
    }

    /**
     * @todo Implement testGetTypes().
     */
    public function testGetTypes()
    {
        $this->assertEquals(array('status'), $this->flashBag->getTypes());
    }

    public function testAll()
    {
        // nothing to do here
    }

    public function testClear()
    {
        $this->assertTrue($this->flashBag->has('status'));
        $this->flashBag->clear('status');
        $this->assertFalse($this->flashBag->has('status'));
    }

    public function testClearAll()
    {
        $this->assertTrue($this->flashBag->has('status'));
        $this->flashBag->add('error', 'Smile, it might work next time');
        $this->assertTrue($this->flashBag->has('error'));
        $this->flashBag->clearAll();
        $this->assertFalse($this->flashBag->has('status'));
        $this->assertFalse($this->flashBag->has('error'));
    }

    public function testPurgeOldFlashes()
    {
        $this->flashBag->add('status', 'Foo');
        $this->flashBag->add('error', 'Bar');
        $this->flashBag->purgeOldFlashes();
        $this->assertEquals(array(1 => 'Foo'), $this->flashBag->get('status'));
    }
        
}