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
use Symfony\Component\DependencyInjection\Config\ContainerParametersResource;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class RouterTest extends TestCase
{
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

        $sc = $this->getServiceContainer($routes);
        $sc->setParameter('locale', 'es');
        $sc->setParameter('foo', 'bar');

        $router = new Router($sc, 'foo');

        $this->assertSame('/en', $router->generate('foo', ['_locale' => 'en']));
        $this->assertSame('/', $router->generate('foo', ['_locale' => 'es']));
        $this->assertSame('"bar" == "bar"', $router->getRouteCollection()->get('foo')->getCondition());
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

        $sc = $this->getServiceContainer($routes);
        $sc->setParameter('parameter.foo', 'foo-%%escaped%%');

        $router = new Router($sc, 'foo');
        $route = $router->getRouteCollection()->get('foo');

        $this->assertEquals(
            '/before/foo-%escaped%/after/%escaped%',
            $route->getPath()
        );
    }

    public function testEnvPlaceholders()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\RuntimeException');
        $this->expectExceptionMessage('Using "%env(FOO)%" is not allowed in routing configuration.');
        $routes = new RouteCollection();

        $routes->add('foo', new Route('/%env(FOO)%'));

        $router = new Router($this->getServiceContainer($routes), 'foo');
        $router->getRouteCollection();
    }

    public function testIndirectEnvPlaceholders()
    {
        $routes = new RouteCollection();

        $routes->add('foo', new Route('/%foo%'));

        $router = new Router($container = $this->getServiceContainer($routes), 'foo');
        $container->setParameter('foo', 'foo-%bar%');
        $container->setParameter('bar', '%env(string:FOO)%');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Using "%env(string:FOO)%" is not allowed in routing configuration.');

        $router->getRouteCollection();
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

    public function testExceptionOnNonExistentParameter()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException');
        $this->expectExceptionMessage('You have requested a non-existent parameter "nope".');
        $routes = new RouteCollection();

        $routes->add('foo', new Route('/%nope%'));

        $sc = $this->getServiceContainer($routes);

        $router = new Router($sc, 'foo');
        $router->getRouteCollection()->get('foo');
    }

    public function testExceptionOnNonStringParameter()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\RuntimeException');
        $this->expectExceptionMessage('The container parameter "object", used in the route configuration value "/%object%", must be a string or numeric, but it is of type "object".');
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

        $sc = $this->getServiceContainer($routes);

        $router = new Router($sc, 'foo');

        $route = $router->getRouteCollection()->get('foo');

        $this->assertSame($value, $route->getDefault('foo'));
    }

    public function testGetRouteCollectionAddsContainerParametersResource()
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
            ->willReturn($routes)
        ;

        $sc = $this->getMockBuilder('Symfony\\Component\\DependencyInjection\\Container')->setMethods(['get'])->getMock();

        $sc
            ->expects($this->once())
            ->method('get')
            ->willReturn($loader)
        ;

        return $sc;
    }
}
