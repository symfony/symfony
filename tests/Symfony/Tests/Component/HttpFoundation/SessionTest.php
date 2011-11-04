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
    protected $storage;
    protected $session;
    
    /**
     * @var \Symfony\Component\HttpFoundation\FlashBagInterface
     */
    protected $flashBag;

    public function setUp()
    {
        $this->storage = new ArraySessionStorage();
        $this->flashBag = new FlashBag();
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

        $this->assertFalse($this->session->has('foo', '/example'));
        $this->assertNull($this->session->get('foo', null, '/example'));
        
        $this->session->set('foo', 'bar');
        $this->assertTrue($this->session->has('foo'));
        $this->assertSame('bar', $this->session->get('foo'));

        // test namespacing
        $this->session->set('foo', 'bar', '/example');
        $this->assertTrue($this->session->has('foo', '/example'));
        $this->assertSame('bar', $this->session->get('foo', '/example'));

        $this->session = $this->getSession();

        $this->session->remove('foo');
        $this->session->set('foo', 'bar');
        $this->session->remove('foo');
        $this->assertFalse($this->session->has('foo'));
        $this->assertTrue($this->session->has('foo', '/example'));
        
        $this->session->remove('foo', '/example');
        $this->session->set('foo', 'bar', '/example');
        $this->session->remove('foo', '/example');
        $this->assertFalse($this->session->has('foo'));
        $this->assertFalse($this->session->has('foo', '/example'));

        $attrs = array('foo' => 'bar', 'bar' => 'foo');

        $this->session = $this->getSession();

        $this->session->replace($attrs);

        $this->assertSame($attrs, $this->session->all());

        $this->session->clear();

        $this->assertSame(array(), $this->session->all());
    }

    public function testMigrateAndInvalidate()
    {
        $this->session->set('foo', 'bar');
        $this->session->getFlashBag()->set('foo', array('bar'));

        $this->assertSame('bar', $this->session->get('foo'));
        $this->assertEquals(array('bar'), $this->session->getFlashBag()->get('foo'));

        $this->session->migrate();

        $this->assertSame('bar', $this->session->get('foo'));
        $this->assertEquals(array('bar'), $this->session->getFlashBag()->get('foo'));

        $this->session = $this->getSession();
        $this->session->invalidate();

        $this->assertSame(array(), $this->session->all());
        $this->assertEquals(array(), $this->session->getFlashBag()->all());
    }

    public function testSerialize()
    {
        $this->session = new Session($this->storage, $this->flashBag);

        $compare = serialize($this->storage);

        $this->assertSame($compare, $this->session->serialize());

        $this->session->unserialize($compare);

        $_storage = new \ReflectionProperty(get_class($this->session), 'storage');
        $_storage->setAccessible(true);

        $this->assertEquals($_storage->getValue($this->session), $this->storage, 'storage match');
    }

    public function testSave()
    {
        $this->storage = new ArraySessionStorage();
        $this->session = new Session($this->storage, $this->flashBag);
        $this->session->set('foo', 'bar');

        $this->session->save();
        $compare = array('_symfony2' => array('attributes' => array('foo' => 'bar'), 'flashes' => array()));

        $r = new \ReflectionObject($this->storage);
        $p = $r->getProperty('data');
        $p->setAccessible(true);

        $this->assertSame($p->getValue($this->storage), $compare);
    }

    public function testGetId()
    {
        $this->assertNull($this->session->getId());
    }

    public function testStart()
    {
        $this->session->start();

        $this->assertSame(array(), $this->session->getFlashBag()->all());
        $this->assertSame(array(), $this->session->all());
    }

    public function testSavedOnDestruct()
    {
        $this->session->set('foo', 'bar');

        $this->session->__destruct();

        $expected = array(
            'attributes'=>array('foo'=>'bar'),
            'flashes'=>array(),
        );
        $saved = $this->storage->read('_symfony2');
        $this->assertSame($expected, $saved);
    }

    public function testSavedOnDestructAfterManualSave()
    {
        $this->session->set('foo', 'nothing');
        $this->session->save();
        $this->session->set('foo', 'bar');

        $this->session->__destruct();

        $expected = array(
            'attributes'=>array('foo'=>'bar'),
            'flashes'=>array(),
        );
        $saved = $this->storage->read('_symfony2');
        $this->assertSame($expected, $saved);
    }

    public function testStorageRegenerate()
    {
        $this->storage->write('foo', 'bar');

        $this->assertTrue($this->storage->regenerate());

        $this->assertEquals('bar', $this->storage->read('foo'));

        $this->assertTrue($this->storage->regenerate(true));

        $this->assertNull($this->storage->read('foo'));
    }

    public function testStorageRemove()
    {
        $this->storage->write('foo', 'bar');

        $this->assertEquals('bar', $this->storage->read('foo'));

        $this->storage->remove('foo');

        $this->assertNull($this->storage->read('foo'));
    }

    protected function getSession()
    {
        return new Session($this->storage, $this->flashBag);
    }
}
