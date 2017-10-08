<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Fragment;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Fragment\FragmentHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group time-sensitive
 */
class FragmentHandlerTest extends TestCase
{
    private $requestStack;

    protected function setUp()
    {
        $this->requestStack = $this->getMockBuilder('Symfony\\Component\\HttpFoundation\\RequestStack')
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $this->requestStack
            ->expects($this->any())
            ->method('getCurrentRequest')
            ->will($this->returnValue(Request::create('/')))
        ;
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testRenderWhenRendererDoesNotExist()
    {
        $handler = new FragmentHandler($this->requestStack);
        $handler->render('/', 'foo');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testRenderWithUnknownRenderer()
    {
        $handler = $this->getHandler($this->returnValue(new Response('foo')));

        $handler->render('/', 'bar');
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Error when rendering "http://localhost/" (Status code is 404).
     */
    public function testDeliverWithUnsuccessfulResponse()
    {
        $handler = $this->getHandler($this->returnValue(new Response('foo', 404)));

        $handler->render('/', 'foo');
    }

    public function testRender()
    {
        $handler = $this->getHandler($this->returnValue(new Response('foo')), array('/', Request::create('/'), array('foo' => 'foo', 'ignore_errors' => true)));

        $this->assertEquals('foo', $handler->render('/', 'foo', array('foo' => 'foo')));
    }

    protected function getHandler($returnValue, $arguments = array())
    {
        $renderer = $this->getMockBuilder('Symfony\Component\HttpKernel\Fragment\FragmentRendererInterface')->getMock();
        $renderer
            ->expects($this->any())
            ->method('getName')
            ->will($this->returnValue('foo'))
        ;
        $e = $renderer
            ->expects($this->any())
            ->method('render')
            ->will($returnValue)
        ;

        if ($arguments) {
            call_user_func_array(array($e, 'with'), $arguments);
        }

        $handler = new FragmentHandler($this->requestStack);
        $handler->addRenderer($renderer);

        return $handler;
    }
}
