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
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArrayStorage;
use Symfony\Bundle\FrameworkBundle\Templating\Helper\SessionHelper;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;

class SessionHelperTest extends \PHPUnit_Framework_TestCase
{
    protected $request;

    public function setUp()
    {
        $this->request = new Request();

        $session = new Session(new MockArrayStorage());
        $session->set('foobar', 'bar');
        $session->getFlashes()->set(FlashBag::NOTICE, 'bar');

        $this->request->setSession($session);
    }

    protected function tearDown()
    {
        $this->request = null;
    }

    public function testFlash()
    {
        $helper = new SessionHelper($this->request);

        $this->assertTrue($helper->hasFlash(FlashBag::NOTICE));

        $this->assertEquals('bar', $helper->getFlash(FlashBag::NOTICE));

        $this->assertEquals(array(FlashBag::NOTICE => 'bar'), $helper->getFlashes());
    }

    public function testGet()
    {
        $helper = new SessionHelper($this->request);

        $this->assertEquals('bar', $helper->get('foobar'));
        $this->assertEquals('foo', $helper->get('bar', 'foo'));

        $this->assertNull($helper->get('foo'));
    }

    public function testGetName()
    {
        $helper = new SessionHelper($this->request);

        $this->assertEquals('session', $helper->getName());
    }
}
