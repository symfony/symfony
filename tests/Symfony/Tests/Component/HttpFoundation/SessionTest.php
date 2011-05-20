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

    public function setUp()
    {
        $this->storage = new ArraySessionStorage();
        $this->session = $this->getSession();
    }

    public function testFlash()
    {
        $this->assertFalse($this->session->hasFlash('foo'));

        $this->session->setFlash('foo', 'bar');

        $this->assertTrue($this->session->hasFlash('foo'));
        $this->assertSame('bar', $this->session->getFlash('foo'));

        $this->session->removeFlash('foo');

        $this->assertFalse($this->session->hasFlash('foo'));

        $flashes = array('foo' => 'bar', 'bar' => 'foo');

        $this->session = $this->getSession();
        $this->session->setFlashes($flashes);

        $this->assertSame($flashes, $this->session->getFlashes());

        $this->session->clearFlashes();

        $this->assertSame(array(), $this->session->getFlashes());
    }

    public function testFlashesAreFlushedWhenNeeded()
    {
        $this->session->setFlash('foo', 'bar');
        $this->session->save();

        $this->session = $this->getSession();
        $this->assertTrue($this->session->hasFlash('foo'));
        $this->session->save();

        $this->session = $this->getSession();
        $this->assertFalse($this->session->hasFlash('foo'));
    }

    public function testAttribute()
    {
        $this->assertFalse($this->session->has('foo'));
        $this->assertNull($this->session->get('foo'));

        $this->session->set('foo', 'bar');

        $this->assertTrue($this->session->has('foo'));
        $this->assertSame('bar', $this->session->get('foo'));

        $this->session = $this->getSession();

        $this->session->remove('foo');
        $this->session->set('foo', 'bar');

        $this->session->remove('foo');

        $this->assertFalse($this->session->has('foo'));

        $attrs = array('foo' => 'bar', 'bar' => 'foo');

        $this->session = $this->getSession();

        $this->session->setAttributes($attrs);

        $this->assertSame($attrs, $this->session->getAttributes());

        $this->session->clear();

        $this->assertSame(array(), $this->session->getAttributes());
    }

    public function testMigrateAndInvalidate()
    {
        $this->session->set('foo', 'bar');
        $this->session->setFlash('foo', 'bar');

        $this->assertSame('bar', $this->session->get('foo'));
        $this->assertSame('bar', $this->session->getFlash('foo'));

        $this->session->migrate();

        $this->assertSame('bar', $this->session->get('foo'));
        $this->assertSame('bar', $this->session->getFlash('foo'));

        $this->session = $this->getSession();
        $this->session->invalidate();

        $this->assertSame(array(), $this->session->getAttributes());
        $this->assertSame(array(), $this->session->getFlashes());
    }

    public function testSerialize()
    {
        $defaultLocale = 'en';
        $this->session = new Session($this->storage, $defaultLocale);

        $compare = serialize(array($this->storage, $defaultLocale));

        $this->assertSame($compare, $this->session->serialize());

        $this->session->unserialize($compare);

        $_defaultLocale = new \ReflectionProperty(get_class($this->session), 'defaultLocale');
        $_defaultLocale->setAccessible(true);

        $_storage = new \ReflectionProperty(get_class($this->session), 'storage');
        $_storage->setAccessible(true);

        $this->assertEquals($_defaultLocale->getValue($this->session), $defaultLocale, 'options match');
        $this->assertEquals($_storage->getValue($this->session), $this->storage, 'storage match');
    }

    public function testSave()
    {
        $this->storage = new ArraySessionStorage();
        $defaultLocale = 'fr';
        $this->session = new Session($this->storage, $defaultLocale);
        $this->session->set('foo', 'bar');

        $this->session->save();
        $compare = array('_symfony2' => array('_flash' => array(), '_locale' => 'fr', 'foo' => 'bar'));

        $r = new \ReflectionObject($this->storage);
        $p = $r->getProperty('data');
        $p->setAccessible(true);

        $this->assertSame($p->getValue($this->storage), $compare);
    }

    public function testLocale()
    {
        $this->assertSame('en', $this->session->getLocale(), 'default locale is en');

        $this->session->set('_locale','de');

        $this->assertSame('de', $this->session->getLocale(), 'locale is de');

        $this->session = $this->getSession();
        $this->session->setLocale('fr');
        $this->assertSame('fr', $this->session->getLocale(), 'locale is fr');
    }

    public function testLocaleAfterClear()
    {
        $this->session->clear();
        $this->assertEquals('en', $this->session->getLocale());
    }

    public function testGetId()
    {
        $this->assertSame(null, $this->session->getId());
    }

    public function testStart()
    {
        $this->session->start();

        $this->assertSame('en', $this->session->getLocale());
        $this->assertSame(array(), $this->session->getFlashes());
        $this->assertSame(array('_flash' => array(), '_locale' => 'en'), $this->session->getAttributes());

        $this->session->start();
        $this->assertSame('en', $this->session->getLocale());
    }

    protected function getSession()
    {
        return new Session($this->storage);
    }
}
