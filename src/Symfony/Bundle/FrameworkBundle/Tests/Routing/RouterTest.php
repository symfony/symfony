<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Routing;

use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class RoutingTest extends \PHPUnit_Framework_TestCase
{
    public function testPlaceholders()
    {
        $routes = new RouteCollection();
        $routes->add('foo', new Route('/foo', array(
            'foo'    => '%foo%',
            'bar'    => '%bar%',
            'foobar' => 'foobar',
            'foo1'   => '%foo',
            'foo2'   => 'foo%',
            'foo3'   => 'f%o%o',
        ), array(
            'foo'    => '%foo%',
            'bar'    => '%bar%',
            'foobar' => 'foobar',
            'foo1'   => '%foo',
            'foo2'   => 'foo%',
            'foo3'   => 'f%o%o',
        )));

        $sc = $this->getServiceContainer($routes);
        $sc->expects($this->at(1))->method('hasParameter')->will($this->returnValue(false));
        $sc->expects($this->at(2))->method('hasParameter')->will($this->returnValue(true));
        $sc->expects($this->at(3))->method('getParameter')->will($this->returnValue('bar'));
        $sc->expects($this->at(4))->method('hasParameter')->will($this->returnValue(false));
        $sc->expects($this->at(5))->method('hasParameter')->will($this->returnValue(true));
        $sc->expects($this->at(6))->method('getParameter')->will($this->returnValue('bar'));

        $router = new Router($sc, 'foo');
        $route = $router->getRouteCollection()->get('foo');

        $this->assertEquals('%foo%', $route->getDefault('foo'));
        $this->assertEquals('bar', $route->getDefault('bar'));
        $this->assertEquals('foobar', $route->getDefault('foobar'));
        $this->assertEquals('%foo', $route->getDefault('foo1'));
        $this->assertEquals('foo%', $route->getDefault('foo2'));
        $this->assertEquals('f%o%o', $route->getDefault('foo3'));

        $this->assertEquals('%foo%', $route->getRequirement('foo'));
        $this->assertEquals('bar', $route->getRequirement('bar'));
        $this->assertEquals('foobar', $route->getRequirement('foobar'));
        $this->assertEquals('%foo', $route->getRequirement('foo1'));
        $this->assertEquals('foo%', $route->getRequirement('foo2'));
        $this->assertEquals('f%o%o', $route->getRequirement('foo3'));
    }

    private function getServiceContainer(RouteCollection $routes)
    {
        $sc = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $sc
            ->expects($this->once())
            ->method('get')
            ->will($this->returnValue($this->getLoader($routes)))
        ;

        return $sc;
    }

    private function getLoader(RouteCollection $routes)
    {
        $loader = $this->getMock('Symfony\Component\Config\Loader\LoaderInterface');
        $loader
            ->expects($this->any())
            ->method('load')
            ->will($this->returnValue($routes))
        ;

        return $loader;
    }
}
