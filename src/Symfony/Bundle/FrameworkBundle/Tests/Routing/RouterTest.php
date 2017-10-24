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

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class RouterTest extends TestCase
{
    public function testGenerateWithServiceParam()
    {
        $routes = new RouteCollection();

        $routes->add('foo', new Route(
            ' /{_locale}',
            array(
                '_locale' => '%locale%',
            ),
            array(
                '_locale' => 'en|es',
            ), array(), '', array(), array(), '"%foo%" == "bar"'
        ));

        $sc = $this->getServiceContainer($routes);
        $sc->setParameter('locale', 'es');
        $sc->setParameter('foo', 'bar');

        $router = new Router($sc, 'foo');

        $this->assertSame('/en', $router->generate('foo', array('_locale' => 'en')));
        $this->assertSame('/', $router->generate('foo', array('_locale' => 'es')));
        $this->assertSame('"bar" == "bar"', $router->getRouteCollection()->get('foo')->getCondition());
    }

    public function testDefaultsPlaceholders()
    {
        $routes = new RouteCollection();

        $routes->add('foo', new Route(
            '/foo',
            array(
                'foo' => 'before_%parameter.foo%',
                'bar' => '%parameter.bar%_after',
                'baz' => '%%escaped%%',
                'boo' => array('%parameter%', '%%escaped_parameter%%', array('%bee_parameter%', 'bee')),
                'bee' => array('bee', 'bee'),
            ),
            array(
            )
        ));

        $sc = $this->getServiceContainer($routes);

        $sc->setParameter('parameter.foo', 'foo');
        $sc->setParameter('parameter.bar', 'bar');
        $sc->setParameter('parameter', 'boo');
        $sc->setParameter('bee_parameter', 'foo_bee');

        $router = new Router($sc, 'foo');
        $route = $router->getRouteCollection()->get('foo');

        $this->assertEquals(
            array(
                'foo' => 'before_foo',
                'bar' => 'bar_after',
                'baz' => '%escaped%',
                'boo' => array('boo', '%escaped_parameter%', array('foo_bee', 'bee')),
                'bee' => array('bee', 'bee'),
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
                'foo' => 'before_%parameter.foo%',
                'bar' => '%parameter.bar%_after',
                'baz' => '%%escaped%%',
            )
        ));

        $sc = $this->getServiceContainer($routes);
        $sc->setParameter('parameter.foo', 'foo');
        $sc->setParameter('parameter.bar', 'bar');

        $router = new Router($sc, 'foo');
        $route = $router->getRouteCollection()->get('foo');

        $this->assertEquals(
            array(
                'foo' => 'before_foo',
                'bar' => 'bar_after',
                'baz' => '%escaped%',
            ),
            $route->getRequirements()
        );
    }

    public function testPatternPlaceholders()
    {
        $routes = new RouteCollection();

        $routes->add('foo', new Route('/before/%parameter.foo%/after/%%escaped%%'));

        $sc = $this->getServiceContainer($routes);
        $sc->setParameter('parameter.foo', 'foo');

        $router = new Router($sc, 'foo');
        $route = $router->getRouteCollection()->get('foo');

        $this->assertEquals(
            '/before/foo/after/%escaped%',
            $route->getPath()
        );
    }

    public function testHostPlaceholders()
    {
        $routes = new RouteCollection();

        $route = new Route('foo');
        $route->setHost('/before/%parameter.foo%/after/%%escaped%%');

        $routes->add('foo', $route);

        $sc = $this->getServiceContainer($routes);
        $sc->setParameter('parameter.foo', 'foo');

        $router = new Router($sc, 'foo');
        $route = $router->getRouteCollection()->get('foo');

        $this->assertEquals(
            '/before/foo/after/%escaped%',
            $route->getHost()
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

        $router = new Router($sc, 'foo');
        $router->getRouteCollection()->get('foo');
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage The container parameter "object", used in the route configuration value "/%object%", must be a string or numeric, but it is of type object.
     */
    public function testExceptionOnNonStringParameter()
    {
        $routes = new RouteCollection();

        $routes->add('foo', new Route('/%object%'));

        $sc = $this->getServiceContainer($routes);
        $sc->setParameter('object', new \stdClass());

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
        return array(array(null), array(false), array(true), array(new \stdClass()), array(array('foo', 'bar')), array(array(array())));
    }

    /**
     * @return \Symfony\Component\DependencyInjection\Container
     */
    private function getServiceContainer(RouteCollection $routes)
    {
        $loader = $this->getMockBuilder('Symfony\Component\Config\Loader\LoaderInterface')->getMock();

        $loader
            ->expects($this->any())
            ->method('load')
            ->will($this->returnValue($routes))
        ;

        $sc = $this->getMockBuilder('Symfony\\Component\\DependencyInjection\\Container')->setMethods(array('get'))->getMock();

        $sc
            ->expects($this->once())
            ->method('get')
            ->will($this->returnValue($loader))
        ;

        return $sc;
    }
}
