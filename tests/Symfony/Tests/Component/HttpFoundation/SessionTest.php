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

    public function setUp()
    {
        $this->flashBag = new FlashBag();
        $this->storage = new ArraySessionStorage($this->flashBag);
        $this->session = $this->getSession();
    }

    protected function tearDown()
    {
        $this->storage = null;
        $this->flashBag = null;
        $this->session = null;
    }

    public function getFlashBag()
    {
        $this->assetTrue($this->getFlashBag() instanceof FlashBagInterface);
    }

    public function testAll()
    {
        $this->assertFalse($this->session->has('foo'));
        $this->assertNull($this->session->get('foo'));

        $this->assertFalse($this->session->has('example.foo'));
        $this->assertNull($this->session->get('example.foo', null));

        $this->session->set('foo', 'bar');
        $this->assertTrue($this->session->has('foo'));
        $this->assertSame('bar', $this->session->get('foo'));

        // test namespacing
        $this->session->set('namespace/example.foo', 'bar');
        $this->assertTrue($this->session->has('namespace/example.foo'));
        $this->assertSame('bar', $this->session->get('namespace/example.foo'));

        $this->session = $this->getSession();

        $this->session->remove('foo');
        $this->session->set('foo', 'bar');
        $this->session->remove('foo');
        $this->assertFalse($this->session->has('foo'));
        $this->assertTrue($this->session->has('namespace/example.foo'));

        $this->session->remove('example.foo');
        $this->session->set('example.foo', 'bar');
        $this->session->remove('example.foo');
        $this->assertFalse($this->session->has('foo'));
        $this->assertFalse($this->session->has('example.foo'));

        $attrs = array('foo' => 'bar', 'bar' => 'foo');

        $this->session = $this->getSession();

        $this->session->replace($attrs);

        $this->assertSame($attrs, $this->session->all());

        $this->session->clear();

        $this->assertSame(array(), $this->session->all());
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
        $this->session = new Session($this->storage);
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
        $this->session = new Session($this->storage);
        $serialized = serialize(new \ArrayObject());
        $this->session->unserialize($serialized);
    }


    public function testGetId()
    {
        $this->assertEquals('', $this->session->getId());
        $this->session->start();
        $this->assertNotEquals('', $this->session->getId());
    }

    public function testStart()
    {
        $this->session->start();
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

    protected function getSession()
    {
        return new Session($this->storage);
    }
}
