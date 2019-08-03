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

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Templating\Helper\SessionHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

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

        $this->assertEquals(['bar'], $helper->getFlash('notice'));
    }

    public function testGetFlashes()
    {
        $helper = new SessionHelper($this->requestStack);
        $this->assertEquals(['notice' => ['bar']], $helper->getFlashes());
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
