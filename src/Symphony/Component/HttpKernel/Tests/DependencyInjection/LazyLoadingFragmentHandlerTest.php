<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symphony\Component\HttpKernel\DependencyInjection\LazyLoadingFragmentHandler;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpFoundation\Response;

class LazyLoadingFragmentHandlerTest extends TestCase
{
    public function testRender()
    {
        $renderer = $this->getMockBuilder('Symphony\Component\HttpKernel\Fragment\FragmentRendererInterface')->getMock();
        $renderer->expects($this->once())->method('getName')->will($this->returnValue('foo'));
        $renderer->expects($this->any())->method('render')->will($this->returnValue(new Response()));

        $requestStack = $this->getMockBuilder('Symphony\Component\HttpFoundation\RequestStack')->getMock();
        $requestStack->expects($this->any())->method('getCurrentRequest')->will($this->returnValue(Request::create('/')));

        $container = $this->getMockBuilder('Psr\Container\ContainerInterface')->getMock();
        $container->expects($this->once())->method('has')->with('foo')->willReturn(true);
        $container->expects($this->once())->method('get')->will($this->returnValue($renderer));

        $handler = new LazyLoadingFragmentHandler($container, $requestStack, false);

        $handler->render('/foo', 'foo');

        // second call should not lazy-load anymore (see once() above on the get() method)
        $handler->render('/foo', 'foo');
    }
}
