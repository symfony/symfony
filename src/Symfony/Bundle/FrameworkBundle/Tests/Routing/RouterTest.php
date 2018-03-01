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
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Config\ContainerParametersResource;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class RouterTest extends TestCase
{
    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage You should either pass a "Symfony\Component\DependencyInjection\ContainerInterface" instance or provide the $parameters argument of the "Symfony\Bundle\FrameworkBundle\Routing\Router::__construct" method
     */
    public function testConstructThrowsOnNonSymfonyNorPsr11Container()
    {
        new Router($this->getMockBuilder(ContainerInterface::class)->getMock(), 'foo');
    }

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

        $sc = $this->getPsr11ServiceContainer($routes);
        $parameters = $this->getParameterBag(array(
            'locale' => 'es',
            'foo' => 'bar',
        ));

        $router = new Router($sc, 'foo', array(), null, $parameters);

        $this->assertSame('/en', $router->generate('foo', array('_locale' => 'en')));
        $this->assertSame('/', $router->generate('foo', array('_locale' => 'es')));
        $this->assertSame('"bar" == "bar"', $router->getRouteCollection()->get('foo')->getCondition());
    }

    public function testGenerateWithServiceParamWithSfContainer()
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

        $sc = $this->getPsr11ServiceContainer($routes);

        $parameters = $this->getParameterBag(array(
            'parameter.foo' => 'foo',
            'parameter.bar' => 'bar',
            'parameter' => 'boo',
            'bee_parameter' => 'foo_bee',
        ));

        $router = new Router($sc, 'foo', array(), null, $parameters);
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

    public function testDefaultsPlaceholdersWithSfContainer()
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

        $sc = $this->getPsr11ServiceContainer($routes);
        $parameters = $this->getParameterBag(array(
            'parameter.foo' => 'foo',
            'parameter.bar' => 'bar',
        ));

        $router = new Router($sc, 'foo', array(), null, $parameters);

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

    public function testRequirementsPlaceholdersWithSfContainer()
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

        $sc = $this->getPsr11ServiceContainer($routes);
        $parameters = $this->getParameterBag(array('parameter.foo' => 'foo'));

        $router = new Router($sc, 'foo', array(), null, $parameters);
        $route = $router->getRouteCollection()->get('foo');

        $this->assertEquals(
            '/before/foo/after/%escaped%',
            $route->getPath()
        );
    }

    public function testPatternPlaceholdersWithSfContainer()
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

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Using "%env(FOO)%" is not allowed in routing configuration.
     */
    public function testEnvPlaceholders()
    {
        $routes = new RouteCollection();

        $routes->add('foo', new Route('/%env(FOO)%'));

        $router = new Router($this->getPsr11ServiceContainer($routes), 'foo', array(), null, $this->getParameterBag());
        $router->getRouteCollection();
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Using "%env(FOO)%" is not allowed in routing configuration.
     */
    public function testEnvPlaceholdersWithSfContainer()
    {
        $routes = new RouteCollection();

        $routes->add('foo', new Route('/%env(FOO)%'));

        $router = new Router($this->getServiceContainer($routes), 'foo');
        $router->getRouteCollection();
    }

    public function testHostPlaceholders()
    {
        $routes = new RouteCollection();

        $route = new Route('foo');
        $route->setHost('/before/%parameter.foo%/after/%%escaped%%');

        $routes->add('foo', $route);

        $sc = $this->getPsr11ServiceContainer($routes);
        $parameters = $this->getParameterBag(array('parameter.foo' => 'foo'));

        $router = new Router($sc, 'foo', array(), null, $parameters);
        $route = $router->getRouteCollection()->get('foo');

        $this->assertEquals(
            '/before/foo/after/%escaped%',
            $route->getHost()
        );
    }

    public function testHostPlaceholdersWithSfContainer()
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
    public function testExceptionOnNonExistentParameterWithSfContainer()
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

        $sc = $this->getPsr11ServiceContainer($routes);
        $parameters = $this->getParameterBag(array('object' => new \stdClass()));

        $router = new Router($sc, 'foo', array(), null, $parameters);
        $router->getRouteCollection()->get('foo');
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage The container parameter "object", used in the route configuration value "/%object%", must be a string or numeric, but it is of type object.
     */
    public function testExceptionOnNonStringParameterWithSfContainer()
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

        $sc = $this->getPsr11ServiceContainer($routes);

        $router = new Router($sc, 'foo', array(), null, $this->getParameterBag());

        $route = $router->getRouteCollection()->get('foo');

        $this->assertSame($value, $route->getDefault('foo'));
    }

    /**
     * @dataProvider getNonStringValues
     */
    public function testDefaultValuesAsNonStringsWithSfContainer($value)
    {
        $routes = new RouteCollection();
        $routes->add('foo', new Route('foo', array('foo' => $value), array('foo' => '\d+')));

        $sc = $this->getServiceContainer($routes);

        $router = new Router($sc, 'foo');

        $route = $router->getRouteCollection()->get('foo');

        $this->assertSame($value, $route->getDefault('foo'));
    }

    public function testGetRouteCollectionAddsContainerParametersResource()
    {
        $routeCollection = new RouteCollection();
        $routeCollection->add('foo', new Route('/%locale%'));

        $sc = $this->getPsr11ServiceContainer($routeCollection);
        $parameters = $this->getParameterBag(array('locale' => 'en'));

        $router = new Router($sc, 'foo', array(), null, $parameters);

        $router->getRouteCollection();
    }

    public function testGetRouteCollectionAddsContainerParametersResourceWithSfContainer()
    {
        $routeCollection = new RouteCollection();
        $routeCollection->add('foo', new Route('/%locale%'));

        $sc = $this->getServiceContainer($routeCollection);
        $sc->setParameter('locale', 'en');

        $router = new Router($sc, 'foo');

        $routeCollection = $router->getRouteCollection();

        $this->assertEquals(array(new ContainerParametersResource(array('locale' => 'en'))), $routeCollection->getResources());
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

    private function getPsr11ServiceContainer(RouteCollection $routes): ContainerInterface
    {
        $loader = $this->getMockBuilder(LoaderInterface::class)->getMock();

        $loader
            ->expects($this->any())
            ->method('load')
            ->will($this->returnValue($routes))
        ;

        $sc = $this->getMockBuilder(ContainerInterface::class)->getMock();

        $sc
            ->expects($this->once())
            ->method('get')
            ->will($this->returnValue($loader))
        ;

        return $sc;
    }

    private function getParameterBag(array $params = array()): ContainerInterface
    {
        $bag = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $bag
            ->expects($this->any())
            ->method('get')
            ->will($this->returnCallback(function ($key) use ($params) {
                return isset($params[$key]) ? $params[$key] : null;
            }))
        ;

        return $bag;
    }
}
