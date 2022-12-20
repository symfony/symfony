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
    private $router = null;

    private $loader = null;

    private $cacheDir;

    protected function setUp(): void
    {
        $this->loader = self::createMock(LoaderInterface::class);
        $this->router = new Router($this->loader, 'routing.yml');

        $this->cacheDir = sys_get_temp_dir().\DIRECTORY_SEPARATOR.uniqid('router_', true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->cacheDir)) {
            array_map('unlink', glob($this->cacheDir.\DIRECTORY_SEPARATOR.'*'));
            @rmdir($this->cacheDir);
        }

        $this->loader = null;
        $this->router = null;
        $this->cacheDir = null;
    }

    public function testSetOptionsWithSupportedOptions()
    {
        $this->router->setOptions([
            'cache_dir' => './cache',
            'debug' => true,
            'resource_type' => 'ResourceType',
        ]);

        self::assertSame('./cache', $this->router->getOption('cache_dir'));
        self::assertTrue($this->router->getOption('debug'));
        self::assertSame('ResourceType', $this->router->getOption('resource_type'));
    }

    public function testSetOptionsWithUnsupportedOptions()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('The Router does not support the following options: "option_foo", "option_bar"');
        $this->router->setOptions([
            'cache_dir' => './cache',
            'option_foo' => true,
            'option_bar' => 'baz',
            'resource_type' => 'ResourceType',
        ]);
    }

    public function testSetOptionWithSupportedOption()
    {
        $this->router->setOption('cache_dir', './cache');

        self::assertSame('./cache', $this->router->getOption('cache_dir'));
    }

    public function testSetOptionWithUnsupportedOption()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('The Router does not support the "option_foo" option');
        $this->router->setOption('option_foo', true);
    }

    public function testGetOptionWithUnsupportedOption()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('The Router does not support the "option_foo" option');
        $this->router->getOption('option_foo', true);
    }

    public function testThatRouteCollectionIsLoaded()
    {
        $this->router->setOption('resource_type', 'ResourceType');

        $routeCollection = new RouteCollection();

        $this->loader->expects(self::once())
            ->method('load')->with('routing.yml', 'ResourceType')
            ->willReturn($routeCollection);

        self::assertSame($routeCollection, $this->router->getRouteCollection());
    }

    public function testMatcherIsCreatedIfCacheIsNotConfigured()
    {
        $this->router->setOption('cache_dir', null);

        $this->loader->expects(self::once())
            ->method('load')->with('routing.yml', null)
            ->willReturn(new RouteCollection());

        self::assertInstanceOf(UrlMatcher::class, $this->router->getMatcher());
    }

    public function testGeneratorIsCreatedIfCacheIsNotConfigured()
    {
        $this->router->setOption('cache_dir', null);

        $this->loader->expects(self::once())
            ->method('load')->with('routing.yml', null)
            ->willReturn(new RouteCollection());

        self::assertInstanceOf(CompiledUrlGenerator::class, $this->router->getGenerator());
    }

    public function testGeneratorIsCreatedIfCacheIsNotConfiguredNotCompiled()
    {
        $this->router->setOption('cache_dir', null);
        $this->router->setOption('generator_class', UrlGenerator::class);

        $this->loader->expects(self::once())
            ->method('load')->with('routing.yml', null)
            ->willReturn(new RouteCollection());

        self::assertInstanceOf(UrlGenerator::class, $this->router->getGenerator());
        self::assertNotInstanceOf(CompiledUrlGenerator::class, $this->router->getGenerator());
    }

    public function testMatchRequestWithUrlMatcherInterface()
    {
        $matcher = self::createMock(UrlMatcherInterface::class);
        $matcher->expects(self::once())->method('match');

        $p = new \ReflectionProperty($this->router, 'matcher');
        $p->setAccessible(true);
        $p->setValue($this->router, $matcher);

        $this->router->matchRequest(Request::create('/'));
    }

    public function testMatchRequestWithRequestMatcherInterface()
    {
        $matcher = self::createMock(RequestMatcherInterface::class);
        $matcher->expects(self::once())->method('matchRequest');

        $p = new \ReflectionProperty($this->router, 'matcher');
        $p->setAccessible(true);
        $p->setValue($this->router, $matcher);

        $this->router->matchRequest(Request::create('/'));
    }

    public function testDefaultLocaleIsPassedToGeneratorClass()
    {
        $this->loader->expects(self::once())
            ->method('load')->with('routing.yml', null)
            ->willReturn(new RouteCollection());

        $router = new Router($this->loader, 'routing.yml', [
            'cache_dir' => null,
        ], null, null, 'hr');

        $generator = $router->getGenerator();

        self::assertInstanceOf(UrlGeneratorInterface::class, $generator);

        $p = new \ReflectionProperty($generator, 'defaultLocale');
        $p->setAccessible(true);

        self::assertSame('hr', $p->getValue($generator));
    }

    public function testDefaultLocaleIsPassedToCompiledGeneratorCacheClass()
    {
        $this->loader->expects(self::once())
            ->method('load')->with('routing.yml', null)
            ->willReturn(new RouteCollection());

        $router = new Router($this->loader, 'routing.yml', [
            'cache_dir' => $this->cacheDir,
        ], null, null, 'hr');

        $generator = $router->getGenerator();

        self::assertInstanceOf(UrlGeneratorInterface::class, $generator);

        $p = new \ReflectionProperty($generator, 'defaultLocale');
        $p->setAccessible(true);

        self::assertSame('hr', $p->getValue($generator));
    }
}
