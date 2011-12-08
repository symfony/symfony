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

use Symfony\Component\HttpFoundation\Session;
use Symfony\Component\HttpFoundation\FlashBag;
use Symfony\Component\HttpFoundation\FlashBagInterface;
use Symfony\Component\HttpFoundation\AttributesBag;
use Symfony\Component\HttpFoundation\AttributesBagInterface;
use Symfony\Component\HttpFoundation\SessionStorage\ArraySessionStorage;

/**
 * SessionTest
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Robert Schönthal <seroscho@googlemail.com>
 */
class SessionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Symfony\Component\HttpFoundation\SessionStorage\SessionStorageInterface
     */
    protected $storage;

    /**
     * @var \Symfony\Component\HttpFoundation\SessionInterface
     */
    protected $session;

    /**
     * @var \Symfony\Component\HttpFoundation\FlashBagInterface
     */
    protected $flashBag;

    /**
     * @var \Symfony\Component\HttpFoundation\AttributesBagInterface
     */
    protected $attributesBag;

    public function setUp()
    {
        $this->flashBag = new FlashBag();
        $this->attributesBag = new AttributesBag();
        $this->storage = new ArraySessionStorage();
        $this->session = new Session($this->storage, $this->attributesBag, $this->flashBag);
    }

    protected function tearDown()
    {
        $this->storage = null;
        $this->flashBag = null;
        $this->attributesBag = null;
        $this->session = null;
    }

    public function test__Constructor()
    {
        // This tests the defaults on the Session object constructor
        $storage = new ArraySessionStorage();
        $session = new Session($storage, $this->attributesBag, $this->flashBag);
        $this->assertSame($this->flashBag, $storage->getFlashBag());
    }

    public function test__ConstructorDefaults()
    {
        // This tests the defaults on the Session object constructor
        $storage = new ArraySessionStorage();
        $session = new Session($storage);
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\FlashBagInterface', $session->getFlashBag());
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\AttributesBagInterface', $storage->getAttributesBag());
    }

    public function testStart()
    {
        $this->assertEquals('', $this->storage->getId());
        $this->session->start();
        $this->assertNotEquals('', $this->storage->getId());
    }

    public function testGetFlashBag()
    {
        $this->assertTrue($this->session->getFlashBag() instanceof FlashBagInterface);
    }

    public function testGet()
    {
        // tests defaults
        $this->assertNull($this->session->get('foo'));
        $this->assertEquals(1, $this->session->get('foo', 1));
    }

    /**
     * @dataProvider setProvider
     */
    public function testSet($key, $value)
    {
        $this->session->set($key, $value);
        $this->assertEquals($value, $this->session->get($key));
    }

    public function testReplace()
    {
        $this->session->replace(array('happiness' => 'be good', 'symfony' => 'awesome'));
        $this->assertEquals(array('happiness' => 'be good', 'symfony' => 'awesome'), $this->session->all());
        $this->session->replace(array());
        $this->assertEquals(array(), $this->session->all());
    }

    /**
     * @dataProvider setProvider
     */
    public function testAll($key, $value, $result)
    {
        $this->session->set($key, $value);
        $this->assertEquals($result, $this->session->all());
    }

    /**
     * @dataProvider setProvider
     */
    public function testClear($key, $value)
    {
        $this->session->set('hi', 'fabien');
        $this->session->set($key, $value);
        $this->session->clear();
        $this->assertEquals(array(), $this->session->all());
    }

    public function setProvider()
    {
        return array(
            array('foo', 'bar', array('foo' => 'bar')),
            array('foo.bar', 'too much beer', array('foo.bar' => 'too much beer')),
            array('great', 'symfony2 is great', array('great' => 'symfony2 is great')),
        );
    }

    /**
     * @dataProvider setProvider
     */
    public function testRemove($key, $value)
    {
       $this->session->set('hi.world', 'have a nice day');
       $this->session->set($key, $value);
       $this->session->remove($key);
       $this->assertEquals(array('hi.world' => 'have a nice day'), $this->session->all());
    }

    public function testInvalidate()
    {
        $this->session->set('invalidate', 123);
        $this->session->getFlashBag()->add('OK');
        $this->session->invalidate();
        $this->assertEquals(array(), $this->session->all());
        $this->assertEquals(array(), $this->session->getFlashBag()->all());
    }

    public function testMigrate()
    {
        $this->session->set('migrate', 321);
        $this->session->getFlashBag()->add('OK');
        $this->session->migrate();
        $this->assertEquals(321, $this->session->get('migrate'));
        $this->assertEquals(array('OK'), $this->session->getFlashBag()->get(FlashBag::NOTICE));
    }

    public function testSerialize()
    {
        $compare = serialize($this->storage);

        $this->assertSame($compare, $this->session->serialize());

        $this->session->unserialize($compare);

        $_storage = new \ReflectionProperty(get_class($this->session), 'storage');
        $_storage->setAccessible(true);

        $this->assertEquals($_storage->getValue($this->session), $this->storage, 'storage match');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testUnserializeException()
    {
        $serialized = serialize(new \ArrayObject());
        $this->session->unserialize($serialized);
    }


    public function testGetId()
    {
        $this->assertEquals('', $this->session->getId());
        $this->session->start();
        $this->assertNotEquals('', $this->session->getId());
    }

    public function flashAdd()
    {
        $this->session->flashAdd('Hello world', FlashBag::NOTICE);
        $this->session->flashAdd('Bye bye cruel world', FlashBag::NOTICE);
        $this->assertEquals(array('Hello world', 'Bye by cruel world'), $this->session->flashGet(FlashBag::NOTICE));
    }

    public function flashGet()
    {
        $this->session->flashAdd('Hello world', FlashBag::NOTICE);
        $this->session->flashAdd('Bye bye cruel world', FlashBag::NOTICE);
        $this->assertEquals(array('Hello world', 'Bye by cruel world'), $this->session->flashGet(FlashBag::NOTICE), true);
        $this->assertEquals(array('Hello world', 'Bye by cruel world'), $this->session->flashGet(FlashBag::NOTICE));
        $this->assertEquals(array(), $this->session->flashGet(FlashBag::NOTICE));
    }
}
