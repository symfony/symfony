<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Command\RouterDebugCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

class RouterDebugCommandTest extends TestCase
{
    public function testSortingByPriority()
    {
        $tester = $this->createCommandTester();
        $result = $tester->execute(['--sort' => 'priority'], ['decorated' => false]);
        $this->assertSame(0, $result, 'Returns 0 in case of success');
        $this->assertRegExp('/(charlie).*\n\W*(alfa).*\n\W*(delta).*\n\W*(bravo)/m', $tester->getDisplay(true));
        $this->assertStringNotContainsString('! [CAUTION] The routes list is not sorted in the parsing order.', $tester->getDisplay(true));
    }

    public function testSortingByName()
    {
        $tester = $this->createCommandTester();
        $result = $tester->execute(['--sort' => 'name'], ['decorated' => false]);
        $this->assertSame(0, $result, 'Returns 0 in case of success');
        $this->assertRegExp('/(alfa).*\n\W*(bravo).*\n\W*(charlie).*\n\W*(delta)/m', $tester->getDisplay(true));
        $this->assertStringContainsString('! [CAUTION] The routes list is not sorted in the parsing order.', $tester->getDisplay(true));
    }

    public function testSortingByPath()
    {
        $tester = $this->createCommandTester();
        $result = $tester->execute(['--sort' => 'path'], ['decorated' => false]);
        $this->assertSame(0, $result, 'Returns 0 in case of success');
        $this->assertRegExp('/(\/romeo).*\n.*(\/sierra).*\n.*(\/tango).*\n.*(\/uniform)/m', $tester->getDisplay(true));
        $this->assertStringContainsString('! [CAUTION] The routes list is not sorted in the parsing order.', $tester->getDisplay(true));
    }

    public function testThrowsExceptionWithInvalidParameter()
    {
        $tester = $this->createCommandTester();
        $this->expectExceptionMessage('The option "foobar" is not valid');
        $tester->execute(['--sort' => 'foobar'], ['decorated' => false]);
    }

    public function testThrowsExceptionWithNullParameter()
    {
        $tester = $this->createCommandTester();
        $this->expectExceptionMessage('The "--sort" option requires a value.');
        $tester->execute(['--sort' => null], ['decorated' => false]);
    }

    public function testSortingByPriorityWithDuplicatePath()
    {
        $tester = $this->createCommandTesterWithDuplicatePath();
        $result = $tester->execute(['--sort' => 'priority'], ['decorated' => false]);
        $this->assertSame(0, $result, 'Returns 0 in case of success');
        $this->assertRegExp('/(charlie).*\n\W*(alfa).*\n\W*(delta).*\n\W*(bravo).*\n\W*(echo)/m', $tester->getDisplay(true));
        $this->assertStringNotContainsString('! [CAUTION] The routes list is not sorted in the parsing order.', $tester->getDisplay(true));
    }

    public function testSortingByNameWithDuplicatePath()
    {
        $tester = $this->createCommandTesterWithDuplicatePath();
        $result = $tester->execute(['--sort' => 'name'], ['decorated' => false]);
        $this->assertSame(0, $result, 'Returns 0 in case of success');
        $this->assertRegExp('/(alfa).*\n\W*(bravo).*\n\W*(charlie).*\n\W*(delta).*\n\W*(echo)/m', $tester->getDisplay(true));
        $this->assertStringContainsString('! [CAUTION] The routes list is not sorted in the parsing order.', $tester->getDisplay(true));
    }

    public function testSortingByPathWithDuplicatePath()
    {
        $tester = $this->createCommandTesterWithDuplicatePath();
        $result = $tester->execute(['--sort' => 'path'], ['decorated' => false]);
        $this->assertSame(0, $result, 'Returns 0 in case of success');
        $this->assertRegExp('/(\/romeo).*\n.*(\/sierra).*\n.*(\/tango).*\n.*(\/uniform).*\n.*(\/uniform)/m', $tester->getDisplay(true));
        $this->assertStringContainsString('! [CAUTION] The routes list is not sorted in the parsing order.', $tester->getDisplay(true));
    }

    public function testWithoutCallingSortOptionExplicitly()
    {
        $tester = $this->createCommandTester();
        $result = $tester->execute([], ['decorated' => false]);
        $this->assertSame(0, $result, 'Returns 0 in case of success');
        $this->assertRegExp('/(charlie).*\n\W*(alfa).*\n\W*(delta).*\n\W*(bravo)/m', $tester->getDisplay(true));
        $this->assertStringNotContainsString('! [CAUTION] The routes list is not sorted in the parsing order.', $tester->getDisplay(true));
    }

    private function createCommandTester(): CommandTester
    {
        $application = new Application($this->getKernel());
        $application->add(new RouterDebugCommand($this->getRouter()));

        return new CommandTester($application->find('debug:router'));
    }

    private function createCommandTesterWithDuplicatePath(): CommandTester
    {
        $application = new Application($this->getKernel());
        $application->add(new RouterDebugCommand($this->getRouterWithDuplicatePath()));

        return new CommandTester($application->find('debug:router'));
    }

    private function getRouter()
    {
        $routeCollection = new RouteCollection();
        $routeCollection->add('charlie', new Route('uniform'));
        $routeCollection->add('alfa', new Route('sierra'));
        $routeCollection->add('delta', new Route('tango'));
        $routeCollection->add('bravo', new Route('romeo'));
        $requestContext = new RequestContext();
        $router = $this->createMock(RouterInterface::class);
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

    private function getRouterWithDuplicatePath()
    {
        $routeCollection = new RouteCollection();
        $routeCollection->add('charlie', (new Route('uniform'))->setMethods('GET'));
        $routeCollection->add('alfa', new Route('sierra'));
        $routeCollection->add('delta', new Route('tango'));
        $routeCollection->add('bravo', new Route('romeo'));
        $routeCollection->add('echo', (new Route('uniform'))->setMethods('POST'));

        $requestContext = new RequestContext();
        $router = $this->createMock(RouterInterface::class);
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
        $container = $this->createMock(ContainerInterface::class);
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

        $kernel = $this->createMock(KernelInterface::class);
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
