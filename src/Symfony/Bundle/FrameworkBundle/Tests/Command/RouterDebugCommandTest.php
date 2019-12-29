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
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class RouterDebugCommandTest extends TestCase
{
    public function testWithoutSortParameter()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute([], ['decorated' => false]);

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertRegExp(
            '/(charlie.*?)(.\s)*(alpha.*?)(.\s)*(bravo.*?).*/mi',
            $tester->getDisplay()
        );
        $this->assertRegExp(
            '/(\/first.*?)(.)*\s(.)*(\/second.*?)(.)*\s(.)*(\/second.*?)(.)*\s(.)*/mi',
            $tester->getDisplay()
        );
    }

    public function testWithSortByPriorityParameter()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['--sort' => 'priority'], ['decorated' => false]);

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertRegExp(
            '/(charlie.*?)(.\s)*(alpha.*?)(.\s)*(bravo.*?).*/mi',
            $tester->getDisplay()
        );
        $this->assertRegExp(
            '/(\/first.*?)(.)*\s(.)*(\/second.*?)(.)*\s(.)*(\/second.*?)(.)*\s(.)*/mi',
            $tester->getDisplay()
        );
    }

    public function testThrowsExceptionOnInvalidParameter()
    {
        $tester = $this->createCommandTester();
        $this->expectException(\InvalidArgumentException::class);
        $tester->execute(['--sort' => 'foobar'], ['decorated' => false]);
    }

    public function testWithSortByNameParameter()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['--sort' => 'name'], ['decorated' => false]);

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertRegExp(
            '/(alpha.*?)(.\s)*(bravo.*?)(.\s)*(charlie.*?).*/mi',
            $tester->getDisplay()
        );
        $this->assertRegExp(
            '/(\/second.*?)(.)*\s(.)*(\/second.*?)(.)*\s(.)*(\/first.*?)(.)*\s(.)*/mi',
            $tester->getDisplay()
        );
    }

    public function testWithSortByPathParameter()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['--sort' => 'path'], ['sort' => 'path', 'decorated' => false]);

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertRegExp(
            '/(charlie.*?)(.\s)*(alpha.*?)(.\s)*(bravo.*?).*/mi',
            $tester->getDisplay()
        );
        $this->assertRegExp(
            '/(\/first.*?)(.)*\s(.)*(\/second.*?)(.)*\s(.)*(\/second.*?)(.)*\s(.)*/mi',
            $tester->getDisplay()
        );
    }

    public function testWithSortByPathParameterDoesNotMissAliases()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['--sort' => 'path'], ['sort' => 'path', 'decorated' => false]);

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertRegExp(
            '/(charlie.*?)(.\s)*(alpha.*?)(.\s)*(bravo.*?).*/mi',
            $tester->getDisplay()
        );
        $this->assertRegExp(
            '/(\/first.*?)(.)*\s(.)*(\/second.*?)(.)*\s(.)*(\/second.*?)(.)*\s(.)*/mi',
            $tester->getDisplay()
        );
    }

    private function createCommandTester(): CommandTester
    {
        $application = new Application($this->getKernel());
        $application->add(new RouterDebugCommand($this->getRouter()));

        return new CommandTester($application->find('debug:router'));
    }

    private function getRouter()
    {
        $routeCollection = new RouteCollection();
        $routeCollection->add('charlie', new Route('first'));
        $routeCollection->add('alpha', new Route('second'));
        $routeCollection->add('bravo', new Route('second'));
        $requestContext = new RequestContext();
        $router = $this->getMockBuilder('Symfony\Component\Routing\RouterInterface')->getMock();
        $router
            ->expects($this->any())
            ->method('getRouteCollection')
            ->willReturn($routeCollection);
        $router
            ->expects($this->any())
            ->method('getContext')
            ->willReturn($requestContext);

        return $router;
    }

    private function getKernel()
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container
            ->expects($this->atLeastOnce())
            ->method('has')
            ->willReturnCallback(function ($id) {
                return 'console.command_loader' !== $id;
            })
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
            ->willReturn([])
        ;

        return $kernel;
    }
}
