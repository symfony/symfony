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

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Bundle\FrameworkBundle\Command\RouterDebugCommand;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class RouterDebugCommandTest extends \PHPUnit_Framework_TestCase
{
    public function testDebugAllRoutes()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(array('name' => null), array('decorated' => false));

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertContains('[router] Current routes', $tester->getDisplay());
    }

    public function testDebugSingleRoute()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(array('name' => 'foo'), array('decorated' => false));

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertContains('[router] Route "foo"', $tester->getDisplay());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testDebugInvalidRoute()
    {
        $this->createCommandTester()->execute(array('name' => 'test'));
    }

    /**
     * @return CommandTester
     */
    private function createCommandTester()
    {
        $application = new Application();

        $command = new RouterDebugCommand();
        $command->setContainer($this->getContainer());
        $application->add($command);

        return new CommandTester($application->find('router:debug'));
    }

    private function getContainer()
    {
        $routeCollection = new RouteCollection();
        $routeCollection->add('foo', new Route('foo'));
        $router = $this->getMock('Symfony\Component\Routing\RouterInterface');
        $router
            ->expects($this->atLeastOnce())
            ->method('getRouteCollection')
            ->will($this->returnValue($routeCollection))
        ;

        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $container
            ->expects($this->once())
            ->method('has')
            ->with('router')
            ->will($this->returnValue(true))
        ;
        $container
            ->expects($this->atLeastOnce())
            ->method('get')
            ->with('router')
            ->will($this->returnValue($router))
        ;

        return $container;
    }
}
