<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests;

use Symfony\Component\HttpKernel\HttpContentRenderer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class HttpContentRendererTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Symfony\Component\EventDispatcher\EventDispatcher')) {
            $this->markTestSkipped('The "EventDispatcher" component is not available');
        }
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testRenderWhenStrategyDoesNotExist()
    {
        $renderer = new HttpContentRenderer();
        $renderer->render('/', 'foo');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testRenderWithUnknownStrategy()
    {
        $strategy = $this->getStrategy($this->returnValue(new Response('foo')));
        $renderer = $this->getRenderer($strategy);

        $renderer->render('/', 'bar');
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage Error when rendering "http://localhost/" (Status code is 404).
     */
    public function testDeliverWithUnsuccessfulResponse()
    {
        $strategy = $this->getStrategy($this->returnValue(new Response('foo', 404)));
        $renderer = $this->getRenderer($strategy);

        $renderer->render('/', 'foo');
    }

    public function testRender()
    {
        $strategy = $this->getStrategy($this->returnValue(new Response('foo')), array('/', Request::create('/'), array('foo' => 'foo', 'ignore_errors' => true)));
        $renderer = $this->getRenderer($strategy);

        $this->assertEquals('foo', $renderer->render('/', 'foo', array('foo' => 'foo')));
    }

    /**
     * @dataProvider getFixOptionsData
     */
    public function testFixOptions($expected, $options)
    {
        $renderer = new HttpContentRenderer();

        set_error_handler(function ($errorNumber, $message, $file, $line, $context) { return $errorNumber & E_USER_DEPRECATED; });
        $this->assertEquals($expected, $renderer->fixOptions($options));
        restore_error_handler();
    }

    public function getFixOptionsData()
    {
        return array(
            array(array('strategy' => 'esi'), array('standalone' => true)),
            array(array('strategy' => 'esi'), array('standalone' => 'esi')),
            array(array('strategy' => 'hinclude'), array('standalone' => 'js')),
        );
    }

    protected function getStrategy($returnValue, $arguments = array())
    {
        $strategy = $this->getMock('Symfony\Component\HttpKernel\RenderingStrategy\RenderingStrategyInterface');
        $strategy
            ->expects($this->any())
            ->method('getName')
            ->will($this->returnValue('foo'))
        ;
        $e = $strategy
            ->expects($this->any())
            ->method('render')
            ->will($returnValue)
        ;

        if ($arguments) {
            call_user_func_array(array($e, 'with'), $arguments);
        }

        return $strategy;
    }

    protected function getRenderer($strategy)
    {
        $renderer = new HttpContentRenderer();
        $renderer->addStrategy($strategy);

        $event = $this->getMockBuilder('Symfony\Component\HttpKernel\Event\GetResponseEvent')->disableOriginalConstructor()->getMock();
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->will($this->returnValue(Request::create('/')))
        ;
        $renderer->onKernelRequest($event);

        return $renderer;
    }
}
