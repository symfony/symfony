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

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArrayStorage;

/**
 * SessionTest
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Robert Schönthal <seroscho@googlemail.com>
 * @author Drak <drak@zikula.org>
 */
class SessionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Symfony\Component\HttpFoundation\Session\Storage\StorageInterface
     */
    protected $storage;

    /**
     * @var \Symfony\Component\HttpFoundation\Session\SessionInterface
     */
    protected $session;

    public function setUp()
    {
        $this->storage = new MockArrayStorage();
        $this->session = new Session($this->storage, new AttributeBag(), new FlashBag());
    }

    protected function tearDown()
    {
        $this->storage = null;
        $this->session = null;
    }

    public function testStart()
    {
        $this->assertEquals('', $this->session->getId());
        $this->assertTrue($this->session->start());
        $this->assertNotEquals('', $this->session->getId());
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
        $this->session->getFlashes()->set(FlashBag::NOTICE, 'OK');
        $this->session->invalidate();
        $this->assertEquals(array(), $this->session->all());
        $this->assertEquals(array(), $this->session->getFlashes()->all());
    }

    public function testMigrate()
    {
        $this->session->set('migrate', 321);
        $this->session->getFlashes()->set(FlashBag::NOTICE, 'HI');
        $this->session->migrate();
        $this->assertEquals(321, $this->session->get('migrate'));
        $this->assertEquals('HI', $this->session->getFlashes()->get(FlashBag::NOTICE));
    }

    public function testMigrateDestroy()
    {
        $this->session->set('migrate', 333);
        $this->session->getFlashes()->set(FlashBag::NOTICE, 'Bye');
        $this->session->migrate(true);
        $this->assertEquals(333, $this->session->get('migrate'));
        $this->assertEquals('Bye', $this->session->getFlashes()->get(FlashBag::NOTICE));
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
}
