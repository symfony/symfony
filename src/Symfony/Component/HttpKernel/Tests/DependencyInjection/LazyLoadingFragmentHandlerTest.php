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
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DependencyInjection\LazyLoadingFragmentHandler;
use Symfony\Component\HttpKernel\Fragment\FragmentRendererInterface;

class LazyLoadingFragmentHandlerTest extends TestCase
{
    public function testRender()
    {
        $renderer = $this->createMock(FragmentRendererInterface::class);
        $renderer->expects($this->once())->method('getName')->willReturn('foo');
        $renderer->expects($this->any())->method('render')->willReturn(new Response());

        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/'));

        $container = new Container();
        $container->set('foo', $renderer);

        $handler = new LazyLoadingFragmentHandler($container, $requestStack, false);

        $handler->render('/foo', 'foo');

        // second call should not lazy-load anymore (see once() above on the get() method)
        $handler->render('/foo', 'foo');
    }
}
