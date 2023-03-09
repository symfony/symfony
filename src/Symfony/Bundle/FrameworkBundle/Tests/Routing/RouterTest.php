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
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\ResourceCheckerConfigCache;
use Symfony\Component\Config\ResourceCheckerConfigCacheFactory;
use Symfony\Component\DependencyInjection\Config\ContainerParametersResource;
use Symfony\Component\DependencyInjection\Config\ContainerParametersResourceChecker;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class RouterTest extends TestCase
{
    public function testConstructThrowsOnNonSymfonyNorPsr11Container()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('You should either pass a "Symfony\Component\DependencyInjection\ContainerInterface" instance or provide the $parameters argument of the "Symfony\Bundle\FrameworkBundle\Routing\Router::__construct" method');
        new Router($this->createMock(ContainerInterface::class), 'foo');
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
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Using "%env(FOO)%" is not allowed in routing configuration.');
        $routes = new RouteCollection();

        $routes->add('foo', new Route('/%env(FOO)%'));

        $router = new Router($this->getPsr11ServiceContainer($routes), 'foo', [], null, $this->getParameterBag());
        $router->getRouteCollection();
    }

    public function testEnvPlaceholdersWithSfContainer()
    {
        $this->expectException(RuntimeException::class);
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

    public function testExceptionOnNonExistentParameterWithSfContainer()
    {
        $this->expectException(ParameterNotFoundException::class);
        $this->expectExceptionMessage('You have requested a non-existent parameter "nope".');
        $routes = new RouteCollection();

        $routes->add('foo', new Route('/%nope%'));

        $sc = $this->getServiceContainer($routes);

        $router = new Router($sc, 'foo');
        $router->getRouteCollection()->get('foo');
    }

    public function testExceptionOnNonStringParameter()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The container parameter "object", used in the route configuration value "/%object%", must be a string or numeric, but it is of type "stdClass".');
        $routes = new RouteCollection();

        $routes->add('foo', new Route('/%object%'));

        $sc = $this->getPsr11ServiceContainer($routes);
        $parameters = $this->getParameterBag(['object' => new \stdClass()]);

        $router = new Router($sc, 'foo', [], null, $parameters);
        $router->getRouteCollection()->get('foo');
    }

    public function testExceptionOnNonStringParameterWithSfContainer()
    {
        $routes = new RouteCollection();

        $routes->add('foo', new Route('/%object%'));

        $sc = $this->getServiceContainer($routes);

        $pc = $this->createMock(ContainerInterface::class);
        $pc
            ->expects($this->once())
            ->method('get')
            ->willReturn(new \stdClass())
        ;

        $router = new Router($sc, 'foo', [], null, $pc);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The container parameter "object", used in the route configuration value "/%object%", must be a string or numeric, but it is of type "stdClass".');

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

    public function testBooleanContainerParametersWithinRouteCondition()
    {
        $routes = new RouteCollection();

        $route = new Route('foo');
        $route->setCondition('%parameter.true% or %parameter.false%');

        $routes->add('foo', $route);

        $sc = $this->getPsr11ServiceContainer($routes);
        $parameters = $this->getParameterBag(['parameter.true' => true, 'parameter.false' => false]);

        $router = new Router($sc, 'foo', [], null, $parameters);
        $route = $router->getRouteCollection()->get('foo');

        $this->assertSame('1 or 0', $route->getCondition());
    }

    public static function getNonStringValues()
    {
        return [[null], [false], [true], [new \stdClass()], [['foo', 'bar']], [[[]]]];
    }

    /**
     * @dataProvider getContainerParameterForRoute
     */
    public function testCacheValidityWithContainerParameters($parameter)
    {
        $cacheDir = sys_get_temp_dir().\DIRECTORY_SEPARATOR.uniqid('router_', true);

        try {
            $container = new Container();
            $container->set('routing.loader', new YamlFileLoader(new FileLocator(__DIR__.'/Fixtures')));

            $container->setParameter('parameter.condition', $parameter);

            $router = new Router($container, 'with_condition.yaml', [
                'debug' => true,
                'cache_dir' => $cacheDir,
            ]);

            $resourceCheckers = [
                new ContainerParametersResourceChecker($container),
            ];

            $router->setConfigCacheFactory(new ResourceCheckerConfigCacheFactory($resourceCheckers));

            $router->getMatcher(); // trigger cache build

            $cache = new ResourceCheckerConfigCache($cacheDir.\DIRECTORY_SEPARATOR.'url_matching_routes.php', $resourceCheckers);

            $this->assertTrue($cache->isFresh());
        } finally {
            if (is_dir($cacheDir)) {
                array_map('unlink', glob($cacheDir.\DIRECTORY_SEPARATOR.'*'));
                rmdir($cacheDir);
            }
        }
    }

    public function testResolvingSchemes()
    {
        $routes = new RouteCollection();

        $route = new Route('/test', [], [], [], '', ['%parameter.http%', '%parameter.https%']);
        $routes->add('foo', $route);

        $sc = $this->getPsr11ServiceContainer($routes);
        $parameters = $this->getParameterBag([
            'parameter.http' => 'http',
            'parameter.https' => 'https',
        ]);

        $router = new Router($sc, 'foo', [], null, $parameters);
        $route = $router->getRouteCollection()->get('foo');

        $this->assertEquals(['http', 'https'], $route->getSchemes());
    }

    public function testResolvingMethods()
    {
        $routes = new RouteCollection();

        $route = new Route('/test', [], [], [], '', [], ['%parameter.get%', '%parameter.post%']);
        $routes->add('foo', $route);

        $sc = $this->getPsr11ServiceContainer($routes);
        $parameters = $this->getParameterBag([
            'PARAMETER.GET' => 'GET',
            'PARAMETER.POST' => 'POST',
        ]);

        $router = new Router($sc, 'foo', [], null, $parameters);
        $route = $router->getRouteCollection()->get('foo');

        $this->assertEquals(['GET', 'POST'], $route->getMethods());
    }

    public static function getContainerParameterForRoute()
    {
        yield 'String' => ['"foo"'];
        yield 'Integer' => [0];
        yield 'Boolean true' => [true];
        yield 'Boolean false' => [false];
    }

    private function getServiceContainer(RouteCollection $routes): Container
    {
        $loader = $this->createMock(LoaderInterface::class);

        $loader
            ->expects($this->any())
            ->method('load')
            ->willReturn($routes)
        ;

        $sc = $this->getMockBuilder(Container::class)->onlyMethods(['get'])->getMock();

        $sc
            ->expects($this->once())
            ->method('get')
            ->willReturn($loader)
        ;

        return $sc;
    }

    private function getPsr11ServiceContainer(RouteCollection $routes): ContainerInterface
    {
        $loader = $this->createMock(LoaderInterface::class);

        $loader
            ->expects($this->any())
            ->method('load')
            ->willReturn($routes)
        ;

        $sc = $this->createMock(ContainerInterface::class);

        $sc
            ->expects($this->once())
            ->method('get')
            ->willReturn($loader)
        ;

        return $sc;
    }

    private function getParameterBag(array $params = []): ContainerInterface
    {
        $bag = $this->createMock(ContainerInterface::class);
        $bag
            ->expects($this->any())
            ->method('get')
            ->willReturnCallback(fn ($key) => $params[$key] ?? null)
        ;

        return $bag;
    }
}
