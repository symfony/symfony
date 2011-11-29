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
use Symfony\Component\HttpFoundation\AttributeBag;
use Symfony\Component\HttpFoundation\AttributeBagInterface;
use Symfony\Component\HttpFoundation\SessionStorage\ArraySessionStorage;

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
     * @var \Symfony\Component\HttpFoundation\SessionStorage\SessionStorageInterface
     */
    protected $storage;

    /**
     * @var \Symfony\Component\HttpFoundation\SessionInterface
     */
    protected $session;

    public function setUp()
    {
        $this->storage = new ArraySessionStorage(new AttributeBag(), new FlashBag());
        $this->session = new Session($this->storage);
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
        $this->session->addFlash('OK');
        $this->session->invalidate();
        $this->assertEquals(array(), $this->session->all());
        $this->assertEquals(array(), $this->session->getAllFlashes());
    }

    public function testMigrate()
    {
        $this->session->set('migrate', 321);
        $this->session->addFlash('HI');
        $this->session->migrate();
        $this->assertEquals(321, $this->session->get('migrate'));
        $this->assertEquals(array('HI'), $this->session->getFlashes(FlashBag::NOTICE));
    }

    public function testMigrateDestroy()
    {
        $this->session->set('migrate', 333);
        $this->session->addFlash('Bye');
        $this->session->migrate(true);
        $this->assertEquals(333, $this->session->get('migrate'));
        $this->assertEquals(array('Bye'), $this->session->getFlashes(FlashBag::NOTICE));
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

    /**
     * @dataProvider provideFlashes
     */
    public function testAddFlash($type, $flashes)
    {
        foreach ($flashes as $message) {
            $this->session->addFlash($message, $type);
        }

        $this->assertEquals($flashes, $this->session->getFlashes($type));
    }

    /**
     * @dataProvider provideFlashes
     */
    public function testGetFlashes($type, $flashes)
    {
        $this->session->setFlashes($type, $flashes);
        $this->assertEquals($flashes, $this->session->getFlashes($type));
    }

    /**
     * @dataProvider provideFlashes
     */
    public function testPopFlashes($type, $flashes)
    {
        $this->session->setFlashes($type, $flashes);
        $this->assertEquals($flashes, $this->session->popFlashes($type));
        $this->assertEquals(array(), $this->session->popFlashes($type));
    }

    /**
     * @dataProvider provideFlashes
     */
    public function testPopAllFlashes($type, $flashes)
    {
        $this->session->setFlashes(FlashBag::NOTICE, array('First', 'Second'));
        $this->session->setFlashes(FlashBag::ERROR, array('Third'));

        $expected = array(
            FlashBag::NOTICE => array('First', 'Second'),
            FlashBag::ERROR => array('Third'),
        );

        $this->assertEquals($expected, $this->session->popAllFlashes());
        $this->assertEquals(array(), $this->session->popAllFlashes());
    }

    public function testSetFlashes()
    {
        $this->session->setFlashes(FlashBag::NOTICE, array('First', 'Second'));
        $this->session->setFlashes(FlashBag::ERROR, array('Third'));
        $this->assertEquals(array('First', 'Second'), $this->session->getFlashes(FlashBag::NOTICE, false));
        $this->assertEquals(array('Third'), $this->session->getFlashes(FlashBag::ERROR, false));
    }

    /**
     * @dataProvider provideFlashes
     */
    public function testHasFlashes($type, $flashes)
    {
        $this->assertFalse($this->session->hasFlashes($type));
        $this->session->setFlashes($type, $flashes);
        $this->assertTrue($this->session->hasFlashes($type));
    }

    /**
     * @dataProvider provideFlashes
     */
    public function testGetFlashKeys($type, $flashes)
    {
        $this->assertEquals(array(), $this->session->getFlashKeys());
        $this->session->setFlashes($type, $flashes);
        $this->assertEquals(array($type), $this->session->getFlashKeys());
    }

    public function testGetFlashKeysBulk()
    {
        $this->loadFlashes();
        $this->assertEquals(array(
            FlashBag::NOTICE, FlashBag::ERROR, FlashBag::WARNING, FlashBag::INFO), $this->session->getFlashKeys()
        );
    }

    public function testGetAllFlashes()
    {
        $this->assertEquals(array(), $this->session->getAllFlashes());

        $this->session->addFlash('a', FlashBag::NOTICE);
        $this->assertEquals(array(
            FlashBag::NOTICE => array('a')
            ), $this->session->getAllFlashes()
        );

        $this->session->addFlash('a', FlashBag::ERROR);
        $this->assertEquals(array(
            FlashBag::NOTICE => array('a'),
            FlashBag::ERROR => array('a'),
            ), $this->session->getAllFlashes());

        $this->session->addFlash('a', FlashBag::WARNING);
        $this->assertEquals(array(
            FlashBag::NOTICE => array('a'),
            FlashBag::ERROR => array('a'),
            FlashBag::WARNING => array('a'),
            ), $this->session->getAllFlashes()
        );

        $this->session->addFlash('a', FlashBag::INFO);
        $this->assertEquals(array(
            FlashBag::NOTICE => array('a'),
            FlashBag::ERROR => array('a'),
            FlashBag::WARNING => array('a'),
            FlashBag::INFO => array('a'),
            ), $this->session->getAllFlashes()
        );

        $this->assertEquals(array(
            FlashBag::NOTICE => array('a'),
            FlashBag::ERROR => array('a'),
            FlashBag::WARNING => array('a'),
            FlashBag::INFO => array('a'),
            ), $this->session->getAllFlashes()
        );
    }

    /**
     * @dataProvider provideFlashes
     */
    public function testClearFlashes($type, $flashes)
    {
        $this->session->setFlashes($type, $flashes);
        $this->session->clearFlashes($type);
        $this->assertEquals(array(), $this->session->getFlashes($type));
    }

    public function testClearAllFlashes()
    {
        $this->loadFlashes();
        $this->assertNotEquals(array(), $this->session->getAllFlashes());
        $this->session->clearAllFlashes();
        $this->assertEquals(array(), $this->session->getAllFlashes());
    }

    protected function loadFlashes()
    {
        $flashes = $this->provideFlashes();
        foreach ($flashes as $data) {
            $this->session->setFlashes($data[0], $data[1]);
        }
    }

    public function provideFlashes()
    {
        return array(
            array(FlashBag::NOTICE, array('a', 'b', 'c')),
            array(FlashBag::ERROR, array('d', 'e', 'f')),
            array(FlashBag::WARNING, array('g', 'h', 'i')),
            array(FlashBag::INFO, array('j', 'k', 'l')),
        );
    }

}
