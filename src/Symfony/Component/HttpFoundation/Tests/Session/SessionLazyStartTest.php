<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Session;

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Storage\LazyMockArraySessionStorage;

/**
 * LazySessionTest
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Robert Schönthal <seroscho@googlemail.com>
 * @author Drak <drak@zikula.org>
 */
class SessionLazyStartTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface
     */
    protected $storage;

    /**
     * @var \Symfony\Component\HttpFoundation\Session\SessionInterface
     */
    protected $session;

    protected function setUp()
    {
        $this->storage = new LazyMockArraySessionStorage();
        $this->session = new Session($this->storage, new AttributeBag(), new FlashBag());
    }

    protected function tearDown()
    {
        $this->storage = null;
        $this->session = null;
    }

    public function testGet()
    {
        // tests defaults
        $this->assertNull($this->session->get('foo'));
        $this->assertEquals(1, $this->session->get('foo', 1));
        $this->assertFalse($this->session->isStarted());
    }

    /**
     * @dataProvider setProvider
     */
    public function testSet($key, $value)
    {
        $this->session->set($key, $value);
        $this->assertEquals($value, $this->session->get($key));
        $this->assertTrue($this->session->isStarted());
    }

    /**
     * @dataProvider setProvider
     */
    public function testHas($key, $value)
    {
        $this->assertFalse($this->session->has($key.'_lazy_start'));
        $this->assertFalse($this->session->isStarted());
    }

    public function testReplace()
    {
        $this->session->replace(array('happiness' => 'be good', 'symfony' => 'awesome'));
        $this->assertTrue($this->session->isStarted());
    }

    /**
     * @dataProvider setProvider
     */
    public function testAll($key, $value, $result)
    {
        $this->assertEquals(array(), $this->session->all());
        $this->session->all();
        $this->assertFalse($this->session->isStarted());
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
        $this->assertTrue($this->session->isStarted());
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
        $this->assertTrue($this->session->isStarted());
    }

    public function testInvalidate()
    {
        $this->session->set('invalidate', 123);
        $this->session->invalidate();
        $this->assertEquals(array(), $this->session->all());
        $this->assertTrue($this->session->isStarted());
    }

    public function testMigrate()
    {
        $this->session->set('migrate', 321);
        $this->session->migrate();
        $this->assertEquals(321, $this->session->get('migrate'));
        $this->assertTrue($this->session->isStarted());
    }

    public function testMigrateDestroy()
    {
        $this->session->set('migrate', 333);
        $this->session->migrate(true);
        $this->assertEquals(333, $this->session->get('migrate'));
        $this->assertTrue($this->session->isStarted());
    }

    /**
     * @covers \Symfony\Component\HttpFoundation\Session\Session::count
     */
    public function testGetCount()
    {
        $this->assertEquals(0, count($this->session));
        $this->assertFalse($this->session->isStarted());
    }
}
