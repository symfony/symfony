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
            [
                '_locale' => '%locale%',
            ],
            [
                '_locale' => 'en|es',
            ], [], '', [], [], '"%foo%" == "bar"'
        ));

        $sc = $this->getPsr11ServiceContainer($routes);
        $parameters = $this->getParameterBag([
            'locale' => 'es',
            'foo' => 'bar',
        ]);

        $router = new Router($sc, 'foo', [], null, $parameters);

        $this->assertSame('/en', $router->generate('foo', ['_locale' => 'en']));
        $this->assertSame('/', $router->generate('foo', ['_locale' => 'es']));
        $this->assertSame('"bar" == "bar"', $router->getRouteCollection()->get('foo')->getCondition());
    }

    public function testGenerateWithServiceParamWithSfContainer()
    {
        $routes = new RouteCollection();

        $routes->add('foo', new Route(
            ' /{_locale}',
            [
                '_locale' => '%locale%',
            ],
            [
                '_locale' => 'en|es',
            ], [], '', [], [], '"%foo%" == "bar"'
        ));

        $sc = $this->getServiceContainer($routes);
        $sc->setParameter('locale', 'es');
        $sc->setParameter('foo', 'bar');

        $router = new Router($sc, 'foo');

        $this->assertSame('/en', $router->generate('foo', ['_locale' => 'en']));
        $this->assertSame('/', $router->generate('foo', ['_locale' => 'es']));
        $this->assertSame('"bar" == "bar"', $router->getRouteCollection()->get('foo')->getCondition());
    }

    public function testGenerateWithDefaultLocale()
    {
        $routes = new RouteCollection();

        $route = new Route('');

        $name = 'testFoo';

        foreach (['hr' => '/test-hr', 'en' => '/test-en'] as $locale => $path) {
            $localizedRoute = clone $route;
            $localizedRoute->setDefault('_locale', $locale);
            $localizedRoute->setDefault('_canonical_route', $name);
            $localizedRoute->setPath($path);
            $routes->add($name.'.'.$locale, $localizedRoute);
        }

        $sc = $this->getServiceContainer($routes);

        $router = new Router($sc, '', [], null, null, null, 'hr');

        $this->assertSame('/test-hr', $router->generate($name));

        $this->assertSame('/test-en', $router->generate($name, ['_locale' => 'en']));
        $this->assertSame('/test-hr', $router->generate($name, ['_locale' => 'hr']));
    }

    public function testDefaultsPlaceholders()
    {
        $routes = new RouteCollection();

        $routes->add('foo', new Route(
            '/foo',
            [
                'foo' => 'before_%parameter.foo%',
                'bar' => '%parameter.bar%_after',
                'baz' => '%%escaped%%',
                'boo' => ['%parameter%', '%%escaped_parameter%%', ['%bee_parameter%', 'bee']],
                'bee' => ['bee', 'bee'],
            ],
            [
            ]
        ));

        $sc = $this->getPsr11ServiceContainer($routes);

        $parameters = $this->getParameterBag([
            'parameter.foo' => 'foo',
            'parameter.bar' => 'bar',
            'parameter' => 'boo',
            'bee_parameter' => 'foo_bee',
        ]);

        $router = new Router($sc, 'foo', [], null, $parameters);
        $route = $router->getRouteCollection()->get('foo');

        $this->assertEquals(
            [
                'foo' => 'before_foo',
                'bar' => 'bar_after',
                'baz' => '%escaped%',
                'boo' => ['boo', '%escaped_parameter%', ['foo_bee', 'bee']],
                'bee' => ['bee', 'bee'],
            ],
            $route->getDefaults()
        );
    }

    public function testDefaultsPlaceholdersWithSfContainer()
    {
        $routes = new RouteCollection();

        $routes->add('foo', new Route(
            '/foo',
            [
                'foo' => 'before_%parameter.foo%',
                'bar' => '%parameter.bar%_after',
                'baz' => '%%escaped%%',
                'boo' => ['%parameter%', '%%escaped_parameter%%', ['%bee_parameter%', 'bee']],
                'bee' => ['bee', 'bee'],
            ],
            [
            ]
        ));

        $sc = $this->getServiceContainer($routes);

        $sc->setParameter('parameter.foo', 'foo');
        $sc->setParameter('parameter.bar', 'bar');
        $sc->setParameter('parameter', 'boo');
        $sc->setParameter('bee_parameter', 'foo_bee');

        $router = new Router($sc, 'foo');
        $route = $router->getRouteCollection()->get('foo');

        $this->assertEquals(
            [
                'foo' => 'before_foo',
                'bar' => 'bar_after',
                'baz' => '%escaped%',
                'boo' => ['boo', '%escaped_parameter%', ['foo_bee', 'bee']],
                'bee' => ['bee', 'bee'],
            ],
            $route->getDefaults()
        );
    }

    public function testRequirementsPlaceholders()
    {
        $routes = new RouteCollection();

        $routes->add('foo', new Route(
            '/foo',
            [
            ],
            [
                'foo' => 'before_%parameter.foo%',
                'bar' => '%parameter.bar%_after',
                'baz' => '%%escaped%%',
            ]
        ));

        $sc = $this->getPsr11ServiceContainer($routes);
        $parameters = $this->getParameterBag([
            'parameter.foo' => 'foo',
            'parameter.bar' => 'bar',
        ]);

        $router = new Router($sc, 'foo', [], null, $parameters);

        $route = $router->getRouteCollection()->get('foo');

        $this->assertEquals(
            [
                'foo' => 'before_foo',
                'bar' => 'bar_after',
                'baz' => '%escaped%',
            ],
            $route->getRequirements()
        );
    }

    public function testRequirementsPlaceholdersWithSfContainer()
    {
        $routes = new RouteCollection();

        $routes->add('foo', new Route(
            '/foo',
            [
            ],
            [
                'foo' => 'before_%parameter.foo%',
                'bar' => '%parameter.bar%_after',
                'baz' => '%%escaped%%',
            ]
        ));

        $sc = $this->getServiceContainer($routes);
        $sc->setParameter('parameter.foo', 'foo');
        $sc->setParameter('parameter.bar', 'bar');

        $router = new Router($sc, 'foo');
        $route = $router->getRouteCollection()->get('foo');

        $this->assertEquals(
            [
                'foo' => 'before_foo',
                'bar' => 'bar_after',
                'baz' => '%escaped%',
            ],
            $route->getRequirements()
        );
    }

    public function testPatternPlaceholders()
    {
        $routes = new RouteCollection();

        $routes->add('foo', new Route('/before/%parameter.foo%/after/%%escaped%%'));

        $sc = $this->getPsr11ServiceContainer($routes);
        $parameters = $this->getParameterBag(['parameter.foo' => 'foo']);

        $router = new Router($sc, 'foo', [], null, $parameters);
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

        $router = new Router($this->getPsr11ServiceContainer($routes), 'foo', [], null, $this->getParameterBag());
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
        $parameters = $this->getParameterBag(['parameter.foo' => 'foo']);

        $router = new Router($sc, 'foo', [], null, $parameters);
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
        $parameters = $this->getParameterBag(['object' => new \stdClass()]);

        $router = new Router($sc, 'foo', [], null, $parameters);
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
        $routes->add('foo', new Route('foo', ['foo' => $value], ['foo' => '\d+']));

        $sc = $this->getPsr11ServiceContainer($routes);

        $router = new Router($sc, 'foo', [], null, $this->getParameterBag());

        $route = $router->getRouteCollection()->get('foo');

        $this->assertSame($value, $route->getDefault('foo'));
    }

    /**
     * @dataProvider getNonStringValues
     */
    public function testDefaultValuesAsNonStringsWithSfContainer($value)
    {
        $routes = new RouteCollection();
        $routes->add('foo', new Route('foo', ['foo' => $value], ['foo' => '\d+']));

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
        $parameters = $this->getParameterBag(['locale' => 'en']);

        $router = new Router($sc, 'foo', [], null, $parameters);

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

        $this->assertEquals([new ContainerParametersResource(['locale' => 'en'])], $routeCollection->getResources());
    }

    public function getNonStringValues()
    {
        return [[null], [false], [true], [new \stdClass()], [['foo', 'bar']], [[[]]]];
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

        $sc = $this->getMockBuilder('Symfony\\Component\\DependencyInjection\\Container')->setMethods(['get'])->getMock();

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

    private function getParameterBag(array $params = []): ContainerInterface
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
