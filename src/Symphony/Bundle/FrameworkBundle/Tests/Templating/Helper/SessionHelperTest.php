<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\Templating\Helper;

use PHPUnit\Framework\TestCase;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpFoundation\RequestStack;
use Symphony\Component\HttpFoundation\Session\Session;
use Symphony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symphony\Bundle\FrameworkBundle\Templating\Helper\SessionHelper;

class SessionHelperTest extends TestCase
{
    protected $requestStack;

    protected function setUp()
    {
        $request = new Request();

        $session = new Session(new MockArraySessionStorage());
        $session->set('foobar', 'bar');
        $session->getFlashBag()->set('notice', 'bar');

        $request->setSession($session);

        $this->requestStack = new RequestStack();
        $this->requestStack->push($request);
    }

    protected function tearDown()
    {
        $this->requestStack = null;
    }

    public function testFlash()
    {
        $helper = new SessionHelper($this->requestStack);

        $this->assertTrue($helper->hasFlash('notice'));

        $this->assertEquals(array('bar'), $helper->getFlash('notice'));
    }

    public function testGetFlashes()
    {
        $helper = new SessionHelper($this->requestStack);
        $this->assertEquals(array('notice' => array('bar')), $helper->getFlashes());
    }

    public function testGet()
    {
        $helper = new SessionHelper($this->requestStack);

        $this->assertEquals('bar', $helper->get('foobar'));
        $this->assertEquals('foo', $helper->get('bar', 'foo'));

        $this->assertNull($helper->get('foo'));
    }

    public function testGetName()
    {
        $helper = new SessionHelper($this->requestStack);

        $this->assertEquals('session', $helper->getName());
    }
}
