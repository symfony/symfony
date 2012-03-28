<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpFoundation\Session;

use Symfony\Component\HttpFoundation\Session\MetaBag;

/**
 * Test class for MetaBag.
 */
class MetaBagTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var MetaBag
     */
    protected $bag;

    /**
     * @var array
     */
    protected $array = array();

    protected function setUp()
    {
        $this->bag = new MetaBag();
        $this->array = array('created' => 1234567, 'lastused' => 12345678);
        $this->bag->initialize($this->array);
    }

    protected function tearDown()
    {
        $this->array = array();
        $this->bag = null;
    }

    public function testInitialize()
    {
        $p = new \ReflectionProperty('Symfony\Component\HttpFoundation\Session\MetaBag', 'meta');
        $p->setAccessible(true);

        $bag1 = new MetaBag();
        $array = array();
        $bag1->initialize($array);
        $this->assertGreaterThanOrEqual(time(), $bag1->getCreated());
        $this->assertEquals($bag1->getCreated(), $bag1->getLastUsed());

        sleep(1);
        $bag2 = new MetaBag();
        $array2 = $p->getValue($bag1);
        $bag2->initialize($array2);
        $this->assertEquals($bag1->getCreated(), $bag2->getCreated());
        $this->assertEquals($bag1->getLastUsed(), $bag2->getLastUsed());
        $this->assertEquals($bag2->getCreated(), $bag2->getLastUsed());

        sleep(1);
        $bag3 = new MetaBag();
        $array3 = $p->getValue($bag2);
        $bag3->initialize($array3);
        $this->assertEquals($bag1->getCreated(), $bag3->getCreated());
        $this->assertGreaterThan($bag2->getLastUsed(), $bag3->getLastUsed());
        $this->assertNotEquals($bag3->getCreated(), $bag3->getLastUsed());
    }

    public function testGetSetName()
    {
        $this->assertEquals('__meta', $this->bag->getName());
        $this->bag->setName('foo');
        $this->assertEquals('foo', $this->bag->getName());

    }

    public function testGetStorageKey()
    {
        $this->assertEquals('_sf2_meta', $this->bag->getStorageKey());
    }

    public function testGetCreated()
    {
        $this->assertEquals(1234567, $this->bag->getCreated());
    }

    public function testGetLastUsed()
    {
        $this->assertLessThanOrEqual(time(), $this->bag->getLastUsed());
    }

    public function testClear()
    {
        $this->bag->clear();
    }

}
