<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\StaticSiteGeneration;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Routing\Exception\InvalidArgumentException;
use Symfony\Component\Routing\Exception\LogicException;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Router;
use Symfony\Component\Routing\StaticSiteGeneration\ParamsProviderInterface;
use Symfony\Component\Routing\StaticSiteGeneration\StaticPageUrisProvider;

class StaticPageUrisProviderTest extends TestCase
{
    public function testProvideUris()
    {
        $routes = new RouteCollection();
        $routes->add('no_config', new Route('/no_config'));
        $routes->add('true_config', new Route('/true_config', ['_stateless' => true, '_static_generation' => true]));
        $routes->add('empty_params', new Route('/empty_params', ['_stateless' => true, '_static_generation' => ['params' => []]]));
        $routes->add('array_params', new Route('/array_params/{param}', ['_stateless' => true, '_static_generation' => ['params' => [['param' => 'foo']]]]));
        $routes->add('extra_param', new Route('/extra_param', ['_stateless' => true, '_static_generation' => ['_stateless' => true, 'params' => [['param' => 'foo']]]]));
        $routes->add('service_param', new Route('/service_param/{param}', ['_stateless' => true, '_static_generation' => ['params' => 'fooParamProvider']]));

        $loader = $this->createStub(LoaderInterface::class);
        $loader->method('load')->willReturn($routes);
        $router = new Router($loader, 'useless');

        $paramProvider = $this->createStub(ParamsProviderInterface::class);
        $paramProvider->method('provideParams')
            ->willReturn([['param' => 'foo']]);

        $paramsProviders = $this->createMock(ContainerInterface::class);
        $paramsProviders->method('get')
            ->with('fooParamProvider')
            ->willReturn($paramProvider);

        $paramsProviders->method('has')
            ->with('fooParamProvider')
            ->willReturn(true);

        $provider = new StaticPageUrisProvider($router, $paramsProviders);

        $this->assertSame([
            '/true_config',
            '/empty_params',
            '/array_params/foo',
            '/extra_param',
            '/service_param/foo',
        ], iterator_to_array($provider->provide()));
    }

    public function testThrowsOnRouteDoesNotAcceptGet()
    {
        $routes = new RouteCollection();
        $routes->add('post_route', new Route(path: '/post_route', defaults: ['_static_generation' => true], methods: ['POST']));

        $loader = $this->createStub(LoaderInterface::class);
        $loader->method('load')->willReturn($routes);
        $router = new Router($loader, 'useless');

        $paramsProviders = $this->createMock(ContainerInterface::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected route "post_route" to accept GET method, it accepts "POST" only.');
        $provider = new StaticPageUrisProvider($router, $paramsProviders);
        iterator_to_array($provider->provide());
    }

    public function testThrowsOnStatelessRoute()
    {
        $routes = new RouteCollection();
        $routes->add('statefull_route', new Route('/statefull_route', ['_static_generation' => true, '_stateless' => false]));

        $loader = $this->createStub(LoaderInterface::class);
        $loader->method('load')->willReturn($routes);
        $router = new Router($loader, 'useless');

        $paramsProviders = $this->createMock(ContainerInterface::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected route "statefull_route" to be stateless');
        $provider = new StaticPageUrisProvider($router, $paramsProviders);
        iterator_to_array($provider->provide());
    }

    public function testThrowsOnMissingParamsConfig()
    {
        $routes = new RouteCollection();
        $routes->add('missing_params', new Route('/missing_params/{param}', ['_stateless' => true, '_static_generation' => ['foo' => 'bar']]));

        $loader = $this->createStub(LoaderInterface::class);
        $loader->method('load')->willReturn($routes);
        $router = new Router($loader, 'useless');

        $paramsProviders = $this->createMock(ContainerInterface::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Missing "params" configuration for route "missing_params"');
        $provider = new StaticPageUrisProvider($router, $paramsProviders);
        iterator_to_array($provider->provide());
    }

    public function testThrowsOnServiceNonExistent()
    {
        $routes = new RouteCollection();
        $routes->add('undefined_service', new Route('/undefined_service/{param}', ['_stateless' => true, '_static_generation' => ['params' => 'undefinedService']]));

        $loader = $this->createStub(LoaderInterface::class);
        $loader->method('load')->willReturn($routes);
        $router = new Router($loader, 'useless');

        $paramsProviders = $this->createMock(ContainerInterface::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('You have requested a non-existent params provider service "undefinedService". Did you implement "Symfony\Component\Routing\StaticSiteGeneration\ParamsProviderInterface"?');
        $provider = new StaticPageUrisProvider($router, $paramsProviders);
        iterator_to_array($provider->provide());
    }

    public function testThrowsOnInvalidParamProvider()
    {
        $routes = new RouteCollection();
        $routes->add('invalid_service', new Route('/invalid_service/{param}', ['_stateless' => true, '_static_generation' => ['params' => 'invalidService']]));

        $loader = $this->createStub(LoaderInterface::class);
        $loader->method('load')->willReturn($routes);
        $router = new Router($loader, 'useless');

        $paramProvider = $this->createStub(\stdClass::class);

        $paramsProviders = $this->createMock(ContainerInterface::class);
        $paramsProviders->method('get')
            ->with('invalidService')
            ->willReturn($paramProvider);

        $paramsProviders->method('has')
            ->with('invalidService')
            ->willReturn(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "invalidService" params provider service does not implement "Symfony\Component\Routing\StaticSiteGeneration\ParamsProviderInterface".');
        $provider = new StaticPageUrisProvider($router, $paramsProviders);
        iterator_to_array($provider->provide());
    }
}
