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

use Symfony\Component\Routing\Loader\ObjectRouteLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class ObjectRouteLoaderTest extends \PHPUnit_Framework_TestCase
{
    public function testLoadCallsServiceAndReturnsCollection()
    {
        $routeLoader = $this->getMock('Symfony\Component\Routing\Loader\RouteLoaderInterface');
        $serviceRouteLoader = new ObjectRouteLoaderForTest();

        $serviceRouteLoader->loaderMap = array(
            'my_route_provider_service' => $routeLoader,
        );

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

        $serviceRouteLoader = new ObjectRouteLoaderForTest();
        $serviceRouteLoader->loaderMap = array(
            'any_service_name' => $routeLoader,
        );

        $serviceRouteLoader->load('any_service_name', 'service');
    }
}

class ObjectRouteLoaderForTest extends ObjectRouteLoader
{
    public $loaderMap = array();

    protected function getRouteLoaderService($id)
    {
        return isset($this->loaderMap[$id]) ? $this->loaderMap[$id] : null;
    }
}
