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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Router;

class RouterTest extends TestCase
{
    private $router = null;

    private $loader = null;

    private $cacheDir;

    protected function setUp(): void
    {
        $this->loader = $this->getMockBuilder('Symfony\Component\Config\Loader\LoaderInterface')->getMock();
        $this->router = new Router($this->loader, 'routing.yml');

        $this->cacheDir = sys_get_temp_dir().\DIRECTORY_SEPARATOR.uniqid('router_', true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->cacheDir)) {
            array_map('unlink', glob($this->cacheDir.\DIRECTORY_SEPARATOR.'*'));
            rmdir($this->cacheDir);
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

        $this->assertSame('./cache', $this->router->getOption('cache_dir'));
        $this->assertTrue($this->router->getOption('debug'));
        $this->assertSame('ResourceType', $this->router->getOption('resource_type'));
    }

    public function testSetOptionsWithUnsupportedOptions()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('The Router does not support the following options: "option_foo", "option_bar"');
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

        $this->assertSame('./cache', $this->router->getOption('cache_dir'));
    }

    public function testSetOptionWithUnsupportedOption()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('The Router does not support the "option_foo" option');
        $this->router->setOption('option_foo', true);
    }

    public function testGetOptionWithUnsupportedOption()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('The Router does not support the "option_foo" option');
        $this->router->getOption('option_foo', true);
    }

    public function testThatRouteCollectionIsLoaded()
    {
        $this->router->setOption('resource_type', 'ResourceType');

        $routeCollection = new RouteCollection();

        $this->loader->expects($this->once())
            ->method('load')->with('routing.yml', 'ResourceType')
            ->willReturn($routeCollection);

        $this->assertSame($routeCollection, $this->router->getRouteCollection());
    }

    public function testMatcherIsCreatedIfCacheIsNotConfigured()
    {
        $this->router->setOption('cache_dir', null);

        $this->loader->expects($this->once())
            ->method('load')->with('routing.yml', null)
            ->willReturn(new RouteCollection());

        $this->assertInstanceOf('Symfony\\Component\\Routing\\Matcher\\UrlMatcher', $this->router->getMatcher());
    }

    public function testGeneratorIsCreatedIfCacheIsNotConfigured()
    {
        $this->router->setOption('cache_dir', null);

        $this->loader->expects($this->once())
            ->method('load')->with('routing.yml', null)
            ->willReturn(new RouteCollection());

        $this->assertInstanceOf('Symfony\\Component\\Routing\\Generator\\UrlGenerator', $this->router->getGenerator());
    }

    public function testMatchRequestWithUrlMatcherInterface()
    {
        $matcher = $this->getMockBuilder('Symfony\Component\Routing\Matcher\UrlMatcherInterface')->getMock();
        $matcher->expects($this->once())->method('match');

        $p = new \ReflectionProperty($this->router, 'matcher');
        $p->setAccessible(true);
        $p->setValue($this->router, $matcher);

        $this->router->matchRequest(Request::create('/'));
    }

    public function testMatchRequestWithRequestMatcherInterface()
    {
        $matcher = $this->getMockBuilder('Symfony\Component\Routing\Matcher\RequestMatcherInterface')->getMock();
        $matcher->expects($this->once())->method('matchRequest');

        $p = new \ReflectionProperty($this->router, 'matcher');
        $p->setAccessible(true);
        $p->setValue($this->router, $matcher);

        $this->router->matchRequest(Request::create('/'));
    }

    public function testDefaultLocaleIsPassedToGeneratorClass()
    {
        $this->loader->expects($this->once())
            ->method('load')->with('routing.yml', null)
            ->willReturn(new RouteCollection());

        $router = new Router($this->loader, 'routing.yml', [
            'cache_dir' => null,
        ], null, null, 'hr');

        $generator = $router->getGenerator();

        $this->assertInstanceOf('Symfony\Component\Routing\Generator\UrlGeneratorInterface', $generator);

        $p = new \ReflectionProperty($generator, 'defaultLocale');
        $p->setAccessible(true);

        $this->assertSame('hr', $p->getValue($generator));
    }

    public function testDefaultLocaleIsPassedToCompiledGeneratorCacheClass()
    {
        $this->loader->expects($this->once())
            ->method('load')->with('routing.yml', null)
            ->willReturn(new RouteCollection());

        $router = new Router($this->loader, 'routing.yml', [
            'cache_dir' => $this->cacheDir,
        ], null, null, 'hr');

        $generator = $router->getGenerator();

        $this->assertInstanceOf('Symfony\Component\Routing\Generator\UrlGeneratorInterface', $generator);

        $p = new \ReflectionProperty($generator, 'defaultLocale');
        $p->setAccessible(true);

        $this->assertSame('hr', $p->getValue($generator));
    }

    /**
     * @group legacy
     */
    public function testDefaultLocaleIsPassedToNotCompiledGeneratorCacheClass()
    {
        $this->loader->expects($this->once())
            ->method('load')->with('routing.yml', null)
            ->willReturn(new RouteCollection());

        $router = new Router($this->loader, 'routing.yml', [
            'cache_dir' => $this->cacheDir,
            'generator_class' => 'Symfony\Component\Routing\Generator\UrlGenerator',
        ], null, null, 'hr');

        $generator = $router->getGenerator();

        $this->assertInstanceOf('Symfony\Component\Routing\Generator\UrlGeneratorInterface', $generator);

        $p = new \ReflectionProperty($generator, 'defaultLocale');
        $p->setAccessible(true);

        $this->assertSame('hr', $p->getValue($generator));
    }
}
