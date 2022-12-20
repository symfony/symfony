<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Command\RouterDebugCommand;
use Symfony\Bundle\FrameworkBundle\Command\RouterMatchCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

class RouterMatchCommandTest extends TestCase
{
    public function testWithMatchPath()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['path_info' => '/foo', 'foo'], ['decorated' => false]);

        self::assertEquals(0, $ret, 'Returns 0 in case of success');
        self::assertStringContainsString('Route Name   | foo', $tester->getDisplay());
    }

    public function testWithNotMatchPath()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['path_info' => '/test', 'foo'], ['decorated' => false]);

        self::assertEquals(1, $ret, 'Returns 1 in case of failure');
        self::assertStringContainsString('None of the routes match the path "/test"', $tester->getDisplay());
    }

    private function createCommandTester(): CommandTester
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
        $router = self::createMock(RouterInterface::class);
        $router
            ->expects(self::any())
            ->method('getRouteCollection')
            ->willReturn($routeCollection);
        $router
            ->expects(self::any())
            ->method('getContext')
            ->willReturn($requestContext);

        return $router;
    }

    private function getKernel()
    {
        $container = self::createMock(ContainerInterface::class);
        $container
            ->expects(self::atLeastOnce())
            ->method('has')
            ->willReturnCallback(function ($id) {
                return 'console.command_loader' !== $id;
            })
        ;
        $container
            ->expects(self::any())
            ->method('get')
            ->with('router')
            ->willReturn($this->getRouter())
        ;

        $kernel = self::createMock(KernelInterface::class);
        $kernel
            ->expects(self::any())
            ->method('getContainer')
            ->willReturn($container)
        ;
        $kernel
            ->expects(self::once())
            ->method('getBundles')
            ->willReturn([])
        ;

        return $kernel;
    }
}
