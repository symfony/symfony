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

use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\CacheWarmer\RouterCacheWarmer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Routing\RouterInterface;

class RouterCacheWarmerTest extends TestCase
{
    public function testWarmUpWithWarmebleInterface()
    {
        $containerMock = $this->getMockBuilder(ContainerInterface::class)->setMethods(array('get', 'has'))->getMock();

        $routerMock = $this->getMockBuilder(testRouterInterfaceWithWarmebleInterface::class)->setMethods(array('match', 'generate', 'getContext', 'setContext', 'getRouteCollection', 'warmUp'))->getMock();
        $containerMock->expects($this->any())->method('get')->with('router')->willReturn($routerMock);
        $routerCacheWarmer = new RouterCacheWarmer($containerMock);

        $routerCacheWarmer->warmUp('/tmp');
        $routerMock->expects($this->any())->method('warmUp')->with('/tmp')->willReturn('');
        $this->addToAssertionCount(1);
    }

    /**
     * @expectedDeprecation Passing a Symfony\Component\Routing\RouterInterface without implementing Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface is deprecated since Symfony 4.1.
     * @group legacy
     */
    public function testWarmUpWithoutWarmebleInterface()
    {
        $containerMock = $this->getMockBuilder(ContainerInterface::class)->setMethods(array('get', 'has'))->getMock();

        $routerMock = $this->getMockBuilder(testRouterInterfaceWithoutWarmebleInterface::class)->setMethods(array('match', 'generate', 'getContext', 'setContext', 'getRouteCollection'))->getMock();
        $containerMock->expects($this->any())->method('get')->with('router')->willReturn($routerMock);
        $routerCacheWarmer = new RouterCacheWarmer($containerMock);
        $routerCacheWarmer->warmUp('/tmp');
    }
}

interface testRouterInterfaceWithWarmebleInterface extends RouterInterface, WarmableInterface
{
}

interface testRouterInterfaceWithoutWarmebleInterface extends RouterInterface
{
}
