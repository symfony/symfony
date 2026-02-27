<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\CompiledUrlGenerator;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Router;

class RouterTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = tempnam(sys_get_temp_dir(), 'sf_router_');
        unlink($this->cacheDir);
        mkdir($this->cacheDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->cacheDir)) {
            array_map('unlink', glob($this->cacheDir.\DIRECTORY_SEPARATOR.'*'));
            @rmdir($this->cacheDir);
        }
    }

    public function testSetOptionsWithSupportedOptions()
    {
        $router = $this->getRouter();
        $router->setOptions([
            'cache_dir' => './cache',
            'debug' => true,
            'resource_type' => 'ResourceType',
        ]);

        $this->assertSame('./cache', $router->getOption('cache_dir'));
        $this->assertTrue($router->getOption('debug'));
        $this->assertSame('ResourceType', $router->getOption('resource_type'));
    }

    public function testSetOptionsWithUnsupportedOptions()
    {
        $router = $this->getRouter();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The Router does not support the following options: "option_foo", "option_bar"');
        $router->setOptions([
            'cache_dir' => './cache',
            'option_foo' => true,
            'option_bar' => 'baz',
            'resource_type' => 'ResourceType',
        ]);
    }

    public function testSetOptionWithSupportedOption()
    {
        $router = $this->getRouter();
        $router->setOption('cache_dir', './cache');

        $this->assertSame('./cache', $router->getOption('cache_dir'));
    }

    public function testSetOptionWithUnsupportedOption()
    {
        $router = $this->getRouter();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The Router does not support the "option_foo" option');
        $router->setOption('option_foo', true);
    }

    public function testGetOptionWithUnsupportedOption()
    {
        $router = $this->getRouter();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The Router does not support the "option_foo" option');
        $router->getOption('option_foo');
    }

    public function testThatRouteCollectionIsLoaded()
    {
        $loader = $this->createMock(LoaderInterface::class);
        $router = $this->getRouter($loader);
        $router->setOption('resource_type', 'ResourceType');

        $routeCollection = new RouteCollection();

        $loader->expects($this->once())
            ->method('load')->with('routing.yml', 'ResourceType')
            ->willReturn($routeCollection);

        $this->assertSame($routeCollection, $router->getRouteCollection());
    }

    public function testMatcherIsCreatedIfCacheIsNotConfigured()
    {
        $loader = $this->createMock(LoaderInterface::class);
        $router = $this->getRouter($loader);
        $router->setOption('cache_dir', null);

        $loader->expects($this->once())
            ->method('load')->with('routing.yml', null)
            ->willReturn(new RouteCollection());

        $this->assertInstanceOf(UrlMatcher::class, $router->getMatcher());
    }

    public function testGeneratorIsCreatedIfCacheIsNotConfigured()
    {
        $loader = $this->createMock(LoaderInterface::class);
        $router = $this->getRouter($loader);
        $router->setOption('cache_dir', null);

        $loader->expects($this->once())
            ->method('load')->with('routing.yml', null)
            ->willReturn(new RouteCollection());

        $this->assertInstanceOf(CompiledUrlGenerator::class, $router->getGenerator());
    }

    public function testGeneratorIsCreatedIfCacheIsNotConfiguredNotCompiled()
    {
        $loader = $this->createMock(LoaderInterface::class);
        $router = $this->getRouter($loader);
        $router->setOption('cache_dir', null);
        $router->setOption('generator_class', UrlGenerator::class);

        $loader->expects($this->once())
            ->method('load')->with('routing.yml', null)
            ->willReturn(new RouteCollection());

        $this->assertInstanceOf(UrlGenerator::class, $router->getGenerator());
        $this->assertNotInstanceOf(CompiledUrlGenerator::class, $router->getGenerator());
    }

    public function testMatchRequestWithUrlMatcherInterface()
    {
        $matcher = $this->createMock(UrlMatcherInterface::class);
        $matcher->expects($this->once())->method('match');

        $router = $this->getRouter();
        $p = new \ReflectionProperty($router, 'matcher');
        $p->setValue($router, $matcher);

        $router->matchRequest(Request::create('/'));
    }

    public function testMatchRequestWithRequestMatcherInterface()
    {
        $matcher = $this->createMock(RequestMatcherInterface::class);
        $matcher->expects($this->once())->method('matchRequest');

        $router = $this->getRouter();
        $p = new \ReflectionProperty($router, 'matcher');
        $p->setValue($router, $matcher);

        $router->matchRequest(Request::create('/'));
    }

    public function testDefaultLocaleIsPassedToGeneratorClass()
    {
        $loader = $this->createMock(LoaderInterface::class);
        $loader->expects($this->once())
            ->method('load')->with('routing.yml', null)
            ->willReturn(new RouteCollection());

        $router = new Router($loader, 'routing.yml', [
            'cache_dir' => null,
        ], null, null, 'hr');

        $generator = $router->getGenerator();

        $this->assertInstanceOf(UrlGeneratorInterface::class, $generator);

        $p = new \ReflectionProperty($generator, 'defaultLocale');

        $this->assertSame('hr', $p->getValue($generator));
    }

    public function testDefaultLocaleIsPassedToCompiledGeneratorCacheClass()
    {
        $loader = $this->createMock(LoaderInterface::class);
        $loader->expects($this->once())
            ->method('load')->with('routing.yml', null)
            ->willReturn(new RouteCollection());

        $router = new Router($loader, 'routing.yml', [
            'cache_dir' => $this->cacheDir,
        ], null, null, 'hr');

        $generator = $router->getGenerator();

        $this->assertInstanceOf(UrlGeneratorInterface::class, $generator);

        $p = new \ReflectionProperty($generator, 'defaultLocale');

        $this->assertSame('hr', $p->getValue($generator));
    }

    private function getRouter(?LoaderInterface $loader = null): Router
    {
        return new Router($loader ?? $this->createStub(LoaderInterface::class), 'routing.yml');
    }
}
