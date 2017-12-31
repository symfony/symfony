<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\DependencyInjection\LazyLoadingFragmentHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LazyLoadingFragmentHandlerTest extends TestCase
{
    /**
     * @group legacy
     * @expectedDeprecation The Symfony\Component\HttpKernel\DependencyInjection\LazyLoadingFragmentHandler::addRendererService() method is deprecated since Symfony 3.3 and will be removed in 4.0.
     */
    public function testRenderWithLegacyMapping()
    {
        $renderer = $this->getMockBuilder('Symfony\Component\HttpKernel\Fragment\FragmentRendererInterface')->getMock();
        $renderer->expects($this->once())->method('getName')->will($this->returnValue('foo'));
        $renderer->expects($this->any())->method('render')->will($this->returnValue(new Response()));

        $requestStack = $this->getMockBuilder('Symfony\Component\HttpFoundation\RequestStack')->getMock();
        $requestStack->expects($this->any())->method('getCurrentRequest')->will($this->returnValue(Request::create('/')));

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container->expects($this->once())->method('get')->will($this->returnValue($renderer));

        $handler = new LazyLoadingFragmentHandler($container, $requestStack, false);
        $handler->addRendererService('foo', 'foo');

        $handler->render('/foo', 'foo');

        // second call should not lazy-load anymore (see once() above on the get() method)
        $handler->render('/foo', 'foo');
    }

    public function testRender()
    {
        $renderer = $this->getMockBuilder('Symfony\Component\HttpKernel\Fragment\FragmentRendererInterface')->getMock();
        $renderer->expects($this->once())->method('getName')->will($this->returnValue('foo'));
        $renderer->expects($this->any())->method('render')->will($this->returnValue(new Response()));

        $requestStack = $this->getMockBuilder('Symfony\Component\HttpFoundation\RequestStack')->getMock();
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
