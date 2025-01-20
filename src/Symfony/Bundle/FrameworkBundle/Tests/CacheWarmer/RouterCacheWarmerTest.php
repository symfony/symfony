<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\CacheWarmer;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\CacheWarmer\RouterCacheWarmer;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Routing\RouterInterface;

class RouterCacheWarmerTest extends TestCase
{
    public function testWarmUpWithWarmableInterfaceWithBuildDir()
    {
        $container = new Container();

        $routerMock = $this->getMockBuilder(testRouterInterfaceWithWarmableInterface::class)->onlyMethods(['match', 'generate', 'getContext', 'setContext', 'getRouteCollection', 'warmUp'])->getMock();
        $routerMock->method('warmUp')->willReturn([]);

        $container->set('router', $routerMock);
        $routerCacheWarmer = new RouterCacheWarmer($container);

        $routerCacheWarmer->warmUp('/tmp/cache', '/tmp/build');
        $routerMock->expects($this->any())->method('warmUp')->with('/tmp/cache', '/tmp/build')->willReturn([]);
        $this->addToAssertionCount(1);
    }

    public function testWarmUpWithoutWarmableInterfaceWithBuildDir()
    {
        $container = new Container();

        $routerMock = $this->getMockBuilder(testRouterInterfaceWithoutWarmableInterface::class)->onlyMethods(['match', 'generate', 'getContext', 'setContext', 'getRouteCollection'])->getMock();
        $container->set('router', $routerMock);
        $routerCacheWarmer = new RouterCacheWarmer($container);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cannot be warmed up because it does not implement "Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface"');
        $routerCacheWarmer->warmUp('/tmp/cache', '/tmp/build');
    }

    public function testWarmUpWithWarmableInterfaceWithoutBuildDir()
    {
        $container = new Container();

        $routerMock = $this->getMockBuilder(testRouterInterfaceWithWarmableInterface::class)->onlyMethods(['match', 'generate', 'getContext', 'setContext', 'getRouteCollection', 'warmUp'])->getMock();
        $container->set('router', $routerMock);
        $routerCacheWarmer = new RouterCacheWarmer($container);

        $preload = $routerCacheWarmer->warmUp('/tmp');
        $routerMock->expects($this->never())->method('warmUp');
        self::assertSame([], $preload);
        $this->addToAssertionCount(1);
    }

    public function testWarmUpWithoutWarmableInterfaceWithoutBuildDir()
    {
        $container = new Container();

        $routerMock = $this->getMockBuilder(testRouterInterfaceWithoutWarmableInterface::class)->onlyMethods(['match', 'generate', 'getContext', 'setContext', 'getRouteCollection'])->getMock();
        $container->set('router', $routerMock);
        $routerCacheWarmer = new RouterCacheWarmer($container);
        $preload = $routerCacheWarmer->warmUp('/tmp');
        self::assertSame([], $preload);
    }
}

interface testRouterInterfaceWithWarmableInterface extends RouterInterface, WarmableInterface
{
}

interface testRouterInterfaceWithoutWarmableInterface extends RouterInterface
{
}
