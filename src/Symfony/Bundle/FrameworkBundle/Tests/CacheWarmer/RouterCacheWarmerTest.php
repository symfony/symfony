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
    public function testWarmUpWithWarmebleInterface()
    {
        $containerMock = $this->getMockBuilder(ContainerInterface::class)->onlyMethods(['get', 'has'])->getMock();

        $routerMock = $this->getMockBuilder(testRouterInterfaceWithWarmebleInterface::class)->onlyMethods(['match', 'generate', 'getContext', 'setContext', 'getRouteCollection', 'warmUp'])->getMock();
        $containerMock->expects($this->any())->method('get')->with('router')->willReturn($routerMock);
        $routerCacheWarmer = new RouterCacheWarmer($containerMock);

        $routerCacheWarmer->warmUp('/tmp');
        $routerMock->expects($this->any())->method('warmUp')->with('/tmp')->willReturn([]);
        $this->addToAssertionCount(1);
    }

    public function testWarmUpWithoutWarmebleInterface()
    {
        $containerMock = $this->getMockBuilder(ContainerInterface::class)->onlyMethods(['get', 'has'])->getMock();

        $routerMock = $this->getMockBuilder(testRouterInterfaceWithoutWarmebleInterface::class)->onlyMethods(['match', 'generate', 'getContext', 'setContext', 'getRouteCollection'])->getMock();
        $containerMock->expects($this->any())->method('get')->with('router')->willReturn($routerMock);
        $routerCacheWarmer = new RouterCacheWarmer($containerMock);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cannot be warmed up because it does not implement "Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface"');
        $routerCacheWarmer->warmUp('/tmp');
    }
}

interface testRouterInterfaceWithWarmebleInterface extends RouterInterface, WarmableInterface
{
}

interface testRouterInterfaceWithoutWarmebleInterface extends RouterInterface
{
}
