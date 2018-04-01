<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symphony\Bundle\FrameworkBundle\Console\Application;
use Symphony\Component\Console\Tester\CommandTester;
use Symphony\Bundle\FrameworkBundle\Command\RouterMatchCommand;
use Symphony\Bundle\FrameworkBundle\Command\RouterDebugCommand;
use Symphony\Component\HttpKernel\KernelInterface;
use Symphony\Component\Routing\Route;
use Symphony\Component\Routing\RouteCollection;
use Symphony\Component\Routing\RequestContext;

class RouterMatchCommandTest extends TestCase
{
    public function testWithMatchPath()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(array('path_info' => '/foo', 'foo'), array('decorated' => false));

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertContains('Route Name   | foo', $tester->getDisplay());
    }

    public function testWithNotMatchPath()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(array('path_info' => '/test', 'foo'), array('decorated' => false));

        $this->assertEquals(1, $ret, 'Returns 1 in case of failure');
        $this->assertContains('None of the routes match the path "/test"', $tester->getDisplay());
    }

    /**
     * @return CommandTester
     */
    private function createCommandTester()
    {
        $application = new Application($this->getKernel());
        $application->add(new RouterMatchCommand($this->getRouter()));
        $application->add(new RouterDebugCommand($this->getRouter()));

        return new CommandTester($application->find('router:match'));
    }

    private function getRouter()
    {
        $routeCollection = new RouteCollection();
        $routeCollection->add('foo', new Route('foo'));
        $requestContext = new RequestContext();
        $router = $this->getMockBuilder('Symphony\Component\Routing\RouterInterface')->getMock();
        $router
            ->expects($this->any())
            ->method('getRouteCollection')
            ->will($this->returnValue($routeCollection));
        $router
            ->expects($this->any())
            ->method('getContext')
            ->will($this->returnValue($requestContext));

        return $router;
    }

    private function getKernel()
    {
        $container = $this->getMockBuilder('Symphony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container
            ->expects($this->atLeastOnce())
            ->method('has')
            ->will($this->returnCallback(function ($id) {
                if ('console.command_loader' === $id) {
                    return false;
                }

                return true;
            }))
        ;
        $container
            ->expects($this->any())
            ->method('get')
            ->with('router')
            ->willReturn($this->getRouter())
        ;

        $kernel = $this->getMockBuilder(KernelInterface::class)->getMock();
        $kernel
            ->expects($this->any())
            ->method('getContainer')
            ->willReturn($container)
        ;
        $kernel
            ->expects($this->once())
            ->method('getBundles')
            ->willReturn(array())
        ;

        return $kernel;
    }
}
