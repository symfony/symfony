<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Templating\Helper;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session;
use Symfony\Component\HttpFoundation\SessionStorage\ArraySessionStorage;
use Symfony\Bundle\FrameworkBundle\Templating\Helper\SessionHelper;

class SessionHelperTest extends \PHPUnit_Framework_TestCase
{
    protected $request;

    public function setUp()
    {
        $this->request = new Request();

        $session = new Session(new ArraySessionStorage());
        $session->set('foobar', 'bar');
        $session->setFlash('foo', 'bar');

        $this->request->setSession($session);
    }

    protected function tearDown()
    {
        $this->request = null;
    }

    public function testFlash()
    {
        $helper = new SessionHelper($this->request);

        $this->assertTrue($helper->hasFlash('foo'));

        $this->assertEquals('bar', $helper->getFlash('foo'));
        $this->assertEquals('foo', $helper->getFlash('bar', 'foo'));

        $this->assertNull($helper->getFlash('foobar'));

        $this->assertEquals(array('foo' => 'bar'), $helper->getFlashes());
    }

    public function testGet()
    {
        $helper = new SessionHelper($this->request);

        $this->assertEquals('bar', $helper->get('foobar'));
        $this->assertEquals('foo', $helper->get('bar', 'foo'));

        $this->assertNull($helper->get('foo'));
    }

    public function testGetLocale()
    {
        $helper = new SessionHelper($this->request);

        $this->assertEquals('en', $helper->getLocale());
    }

    public function testGetName()
    {
        $helper = new SessionHelper($this->request);

        $this->assertEquals('session', $helper->getName());
    }
}
