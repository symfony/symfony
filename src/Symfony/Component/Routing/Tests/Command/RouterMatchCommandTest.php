<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Routing\Command\RouterMatchCommand;
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

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString('Route "foo" matches', $tester->getDisplay());
        $this->assertStringContainsString('debug:router called with "foo"', $tester->getDisplay());
    }

    public function testWithNotMatchPath()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['path_info' => '/test', 'foo'], ['decorated' => false]);

        $this->assertEquals(1, $ret, 'Returns 1 in case of failure');
        $this->assertStringContainsString('None of the routes match the path "/test"', $tester->getDisplay());
    }

    private function createCommandTester(): CommandTester
    {
        $application = new Application();
        $application->addCommand(new RouterMatchCommand($this->getRouter()));
        $application->addCommand(new RouterDebugCommandStub());

        return new CommandTester($application->find('router:match'));
    }

    private function getRouter()
    {
        $routeCollection = new RouteCollection();
        $routeCollection->add('foo', new Route('foo'));
        $requestContext = new RequestContext();
        $router = $this->createStub(RouterInterface::class);
        $router
            ->method('getRouteCollection')
            ->willReturn($routeCollection);
        $router
            ->method('getContext')
            ->willReturn($requestContext);

        return $router;
    }
}

#[AsCommand(name: 'debug:router')]
class RouterDebugCommandStub extends Command
{
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(\sprintf('debug:router called with "%s"', $input->getArgument('name')));

        return 0;
    }
}
