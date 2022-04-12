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
use Symfony\Bundle\FrameworkBundle\Controller\ControllerNameParser;
use Symfony\Bundle\FrameworkBundle\Routing\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouteCompiler;

class DelegatingLoaderTest extends TestCase
{
    /**
     * @group legacy
     * @expectedDeprecation Passing a "Symfony\Bundle\FrameworkBundle\Controller\ControllerNameParser" instance as first argument to "Symfony\Bundle\FrameworkBundle\Routing\DelegatingLoader::__construct()" is deprecated since Symfony 4.4, pass a "Symfony\Component\Config\Loader\LoaderResolverInterface" instance instead.
     */
    public function testConstructorApi()
    {
        $controllerNameParser = $this->createMock(ControllerNameParser::class);
        new DelegatingLoader($controllerNameParser, new LoaderResolver());
        $this->assertTrue(true, '__construct() takes a ControllerNameParser and LoaderResolverInterface respectively as its first and second argument.');
    }

    public function testLoadDefaultOptions()
    {
        $loaderResolver = $this->createMock(LoaderResolverInterface::class);

        $loader = $this->createMock(LoaderInterface::class);

        $loaderResolver->expects($this->once())
            ->method('resolve')
            ->willReturn($loader);

        $routeCollection = new RouteCollection();
        $routeCollection->add('foo', new Route('/', [], [], ['utf8' => false]));
        $routeCollection->add('bar', new Route('/', [], [], ['foo' => 123]));

        $loader->expects($this->once())
            ->method('load')
            ->willReturn($routeCollection);

        $delegatingLoader = new DelegatingLoader($loaderResolver, ['utf8' => true]);

        $loadedRouteCollection = $delegatingLoader->load('foo');
        $this->assertCount(2, $loadedRouteCollection);

        $expected = [
            'compiler_class' => RouteCompiler::class,
            'utf8' => false,
        ];
        $this->assertSame($expected, $routeCollection->get('foo')->getOptions());

        $expected = [
            'compiler_class' => RouteCompiler::class,
            'foo' => 123,
            'utf8' => true,
        ];
        $this->assertSame($expected, $routeCollection->get('bar')->getOptions());
    }

    /**
     * @group legacy
     * @expectedDeprecation Referencing controllers with foo:bar:baz is deprecated since Symfony 4.1, use "some_parsed::controller" instead.
     */
    public function testLoad()
    {
        $controllerNameParser = $this->createMock(ControllerNameParser::class);
        $controllerNameParser->expects($this->once())
            ->method('parse')
            ->with('foo:bar:baz')
            ->willReturn('some_parsed::controller');

        $loaderResolver = $this->createMock(LoaderResolverInterface::class);

        $loader = $this->createMock(LoaderInterface::class);

        $loaderResolver->expects($this->once())
            ->method('resolve')
            ->willReturn($loader);

        $routeCollection = new RouteCollection();
        $routeCollection->add('foo', new Route('/', ['_controller' => 'foo:bar:baz']));
        $routeCollection->add('bar', new Route('/', ['_controller' => 'foo::baz']));
        $routeCollection->add('baz', new Route('/', ['_controller' => 'foo:baz']));

        $loader->expects($this->once())
            ->method('load')
            ->willReturn($routeCollection);

        $delegatingLoader = new DelegatingLoader($controllerNameParser, $loaderResolver);

        $loadedRouteCollection = $delegatingLoader->load('foo');
        $this->assertCount(3, $loadedRouteCollection);
        $this->assertSame('some_parsed::controller', $routeCollection->get('foo')->getDefault('_controller'));
        $this->assertSame('foo::baz', $routeCollection->get('bar')->getDefault('_controller'));
        $this->assertSame('foo:baz', $routeCollection->get('baz')->getDefault('_controller'));
    }
}
