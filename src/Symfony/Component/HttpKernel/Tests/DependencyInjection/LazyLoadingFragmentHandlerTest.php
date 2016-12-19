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

use Symfony\Component\HttpKernel\DependencyInjection\LazyLoadingFragmentHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LazyLoadingFragmentHandlerTest extends \PHPUnit_Framework_TestCase
{
    public function test()
    {
        $renderer = $this->getMockBuilder('Symfony\Component\HttpKernel\Fragment\FragmentRendererInterface')->getMock();
        $renderer->expects($this->once())->method('getName')->will($this->returnValue('foo'));
        $renderer->expects($this->any())->method('render')->will($this->returnValue(new Response()));

        $requestStack = $this->getMockBuilder('Symfony\Component\HttpFoundation\RequestStack')->getMock();
        $requestStack->expects($this->any())->method('getCurrentRequest')->will($this->returnValue(Request::create('/')));

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container->expects($this->once())->method('get')->will($this->returnValue($renderer));

        $handler = new LazyLoadingFragmentHandler($container, false, $requestStack);
        $handler->addRendererService('foo', 'foo');

        $handler->render('/foo', 'foo');

        // second call should not lazy-load anymore (see once() above on the get() method)
        $handler->render('/foo', 'foo');
    }
}
