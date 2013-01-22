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

    public function testRender()
    {
        $request = Request::create('/');

        $strategy = $this->getMock('Symfony\Component\HttpKernel\RenderingStrategy\RenderingStrategyInterface');
        $strategy
            ->expects($this->any())
            ->method('getName')
            ->will($this->returnValue('foo'))
        ;
        $strategy
            ->expects($this->any())
            ->method('render')
            ->with('/', $request, array('foo' => 'foo', 'ignore_errors' => true))
            ->will($this->returnValue(new Response('foo')))
        ;

        $renderer = new HttpContentRenderer();
        $renderer->addStrategy($strategy);

        // simulate a master request
        $event = $this->getMockBuilder('Symfony\Component\HttpKernel\Event\GetResponseEvent')->disableOriginalConstructor()->getMock();
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->will($this->returnValue(Request::create('/')))
        ;
        $renderer->onKernelRequest($event);

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
}
