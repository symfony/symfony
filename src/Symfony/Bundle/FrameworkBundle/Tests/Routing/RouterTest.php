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
    public function testDefaultsPlaceholders()
    {
        $routes = new RouteCollection();

        $routes->add('foo', new Route(
            '/foo',
            array(
                'foo'    => 'before_%parameter.foo%',
                'bar'    => '%parameter.bar%_after',
                'baz'    => '%%unescaped%%',
            ),
            array(
            )
        ));

        $sc = $this->getServiceContainer($routes);

        $sc->expects($this->at(1))->method('hasParameter')->will($this->returnValue(true));
        $sc->expects($this->at(2))->method('getParameter')->will($this->returnValue('foo'));
        $sc->expects($this->at(3))->method('hasParameter')->will($this->returnValue(true));
        $sc->expects($this->at(4))->method('getParameter')->will($this->returnValue('bar'));

        $router = new Router($sc, 'foo');
        $route = $router->getRouteCollection()->get('foo');

        $this->assertEquals(
            array(
                'foo' => 'before_foo',
                'bar' => 'bar_after',
                'baz' => '%unescaped%',
            ),
            $route->getDefaults()
        );
    }

    public function testRequirementsPlaceholders()
    {
        $routes = new RouteCollection();

        $routes->add('foo', new Route(
            '/foo',
            array(
            ),
            array(
                'foo'    => 'before_%parameter.foo%',
                'bar'    => '%parameter.bar%_after',
                'baz'    => '%%unescaped%%',
            )
        ));

        $sc = $this->getServiceContainer($routes);

        $sc->expects($this->at(1))->method('hasParameter')->with('parameter.foo')->will($this->returnValue(true));
        $sc->expects($this->at(2))->method('getParameter')->with('parameter.foo')->will($this->returnValue('foo'));
        $sc->expects($this->at(3))->method('hasParameter')->with('parameter.bar')->will($this->returnValue(true));
        $sc->expects($this->at(4))->method('getParameter')->with('parameter.bar')->will($this->returnValue('bar'));

        $router = new Router($sc, 'foo');
        $route = $router->getRouteCollection()->get('foo');

        $this->assertEquals(
            array(
                'foo' => 'before_foo',
                'bar' => 'bar_after',
                'baz' => '%unescaped%',
            ),
            $route->getRequirements()
        );
    }

    public function testPatternPlaceholders()
    {
        $routes = new RouteCollection();

        $routes->add('foo', new Route('/before/%parameter.foo%/after/%%unescaped%%'));

        $sc = $this->getServiceContainer($routes);

        $sc->expects($this->at(1))->method('hasParameter')->with('parameter.foo')->will($this->returnValue(true));
        $sc->expects($this->at(2))->method('getParameter')->with('parameter.foo')->will($this->returnValue('foo'));

        $router = new Router($sc, 'foo');
        $route = $router->getRouteCollection()->get('foo');

        $this->assertEquals(
            '/before/foo/after/%unescaped%',
            $route->getPattern()
        );
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException
     * @expectedExceptionMessage You have requested a non-existent parameter "nope".
     */
    public function testExceptionOnNonExistentParameter()
    {
        $routes = new RouteCollection();

        $routes->add('foo', new Route('/%nope%'));

        $sc = $this->getServiceContainer($routes);

        $sc->expects($this->at(1))->method('hasParameter')->with('nope')->will($this->returnValue(false));

        $router = new Router($sc, 'foo');
        $router->getRouteCollection()->get('foo');
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage  A string value must be composed of strings and/or numbers,but found parameter "object" of type object inside string value "/%object%".
     */
    public function testExceptionOnNonStringParameter()
    {
        $routes = new RouteCollection();

        $routes->add('foo', new Route('/%object%'));

        $sc = $this->getServiceContainer($routes);

        $sc->expects($this->at(1))->method('hasParameter')->with('object')->will($this->returnValue(true));
        $sc->expects($this->at(2))->method('getParameter')->with('object')->will($this->returnValue(new \stdClass()));

        $router = new Router($sc, 'foo');
        $router->getRouteCollection()->get('foo');
    }

    /**
     * @dataProvider getNonStringValues
     */
    public function testDefaultValuesAsNonStrings($value)
    {
        $routes = new RouteCollection();
        $routes->add('foo', new Route('foo', array('foo' => $value), array('foo' => '\d+')));

        $sc = $this->getServiceContainer($routes);

        $router = new Router($sc, 'foo');

        $route = $router->getRouteCollection()->get('foo');

        $this->assertSame($value, $route->getDefault('foo'));
    }

    public function getNonStringValues()
    {
        return array(array(null), array(false), array(true), array(new \stdClass()), array(array('foo', 'bar')));
    }

    private function getServiceContainer(RouteCollection $routes)
    {
        $loader = $this->getMock('Symfony\Component\Config\Loader\LoaderInterface');

        $loader
            ->expects($this->any())
            ->method('load')
            ->will($this->returnValue($routes))
        ;

        $sc = $this->getMock('Symfony\\Component\\DependencyInjection\\ContainerInterface');

        $sc
            ->expects($this->once())
            ->method('get')
            ->will($this->returnValue($loader))
        ;

        return $sc;
    }
}
