<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Loader;

use Symfony\Component\Routing\Loader\ServiceRouterLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class ServiceRouterLoaderTest extends \PHPUnit_Framework_TestCase
{
    public function testLoadCallsServiceAndReturnsCollection()
    {
        $routeLoader = $this->getMock('Symfony\Component\Routing\Loader\RouteLoaderInterface');

        $container = $this->getMock('Symfony\\Component\\DependencyInjection\\ContainerInterface', array('get'));

        $container
            ->expects($this->any())
            ->method('get')
            ->with('my_route_provider_service')
            ->will($this->returnValue($routeLoader))
        ;

        $serviceRouteLoader = new ServiceRouterLoader($container);

        // create a basic collection that will be returned
        $routes = new RouteCollection();
        $routes->add('foo', new Route('/foo'));

        $routeLoader
            ->expects($this->any())
            ->method('getRouteCollection')
            // the loader itself is passed
            ->with($serviceRouteLoader)
            ->will($this->returnValue($routes));

        $actualRoutes = $serviceRouteLoader->load('my_route_provider_service', 'service');

        $this->assertSame($routes, $actualRoutes);
        // the service file should be listed as a resource
        $this->assertNotEmpty($actualRoutes->getResources());
    }

    /**
     * @expectedException \LogicException
     */
    public function testExceptionOnInterfaceNotImplemented()
    {
        // anything that doesn't implement the interface
        $routeLoader = new \stdClass();

        $container = $this->getMock('Symfony\\Component\\DependencyInjection\\ContainerInterface', array('get'));

        $container
            ->expects($this->once())
            ->method('get')
            ->will($this->returnValue($routeLoader))
        ;

        $serviceRouteLoader = new ServiceRouterLoader($container);
        $serviceRouteLoader->load('any_service_name', 'service');
    }
}
