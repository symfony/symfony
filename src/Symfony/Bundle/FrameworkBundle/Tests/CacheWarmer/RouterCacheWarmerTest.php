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
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\CacheWarmer\RouterCacheWarmer;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Routing\RouterInterface;

class RouterCacheWarmerTest extends TestCase
{
    public function testWarmUpWithWarmableInterfaceWithBuildDir()
    {
        $containerMock = $this->getMockBuilder(ContainerInterface::class)->onlyMethods(['get', 'has'])->getMock();

        $routerMock = $this->getMockBuilder(testRouterInterfaceWithWarmableInterface::class)->onlyMethods(['match', 'generate', 'getContext', 'setContext', 'getRouteCollection', 'warmUp'])->getMock();
        $containerMock->expects($this->any())->method('get')->with('router')->willReturn($routerMock);
        $routerCacheWarmer = new RouterCacheWarmer($containerMock);

        $routerCacheWarmer->warmUp('/tmp/cache', '/tmp/build');
        $routerMock->expects($this->any())->method('warmUp')->with('/tmp/cache', '/tmp/build')->willReturn([]);
        $this->addToAssertionCount(1);
    }

    public function testWarmUpWithoutWarmableInterfaceWithBuildDir()
    {
        $containerMock = $this->getMockBuilder(ContainerInterface::class)->onlyMethods(['get', 'has'])->getMock();

        $routerMock = $this->getMockBuilder(testRouterInterfaceWithoutWarmableInterface::class)->onlyMethods(['match', 'generate', 'getContext', 'setContext', 'getRouteCollection'])->getMock();
        $containerMock->expects($this->any())->method('get')->with('router')->willReturn($routerMock);
        $routerCacheWarmer = new RouterCacheWarmer($containerMock);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cannot be warmed up because it does not implement "Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface"');
        $routerCacheWarmer->warmUp('/tmp/cache', '/tmp/build');
    }

    public function testWarmUpWithWarmableInterfaceWithoutBuildDir()
    {
        $containerMock = $this->getMockBuilder(ContainerInterface::class)->onlyMethods(['get', 'has'])->getMock();

        $routerMock = $this->getMockBuilder(testRouterInterfaceWithWarmableInterface::class)->onlyMethods(['match', 'generate', 'getContext', 'setContext', 'getRouteCollection', 'warmUp'])->getMock();
        $containerMock->expects($this->any())->method('get')->with('router')->willReturn($routerMock);
        $routerCacheWarmer = new RouterCacheWarmer($containerMock);

        $preload = $routerCacheWarmer->warmUp('/tmp');
        $routerMock->expects($this->never())->method('warmUp');
        self::assertSame([], $preload);
        $this->addToAssertionCount(1);
    }

    public function testWarmUpWithoutWarmableInterfaceWithoutBuildDir()
    {
        $containerMock = $this->getMockBuilder(ContainerInterface::class)->onlyMethods(['get', 'has'])->getMock();

        $routerMock = $this->getMockBuilder(testRouterInterfaceWithoutWarmableInterface::class)->onlyMethods(['match', 'generate', 'getContext', 'setContext', 'getRouteCollection'])->getMock();
        $containerMock->expects($this->any())->method('get')->with('router')->willReturn($routerMock);
        $routerCacheWarmer = new RouterCacheWarmer($containerMock);
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
