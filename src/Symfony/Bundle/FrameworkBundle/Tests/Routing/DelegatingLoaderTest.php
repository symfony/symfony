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
use Symfony\Bundle\FrameworkBundle\Routing\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouteCompiler;

class DelegatingLoaderTest extends TestCase
{
    public function testConstructorApi()
    {
        new DelegatingLoader(new LoaderResolver());
        $this->assertTrue(true, '__construct() takes a LoaderResolverInterface as its first argument.');
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
        $routeCollection->add('bar', new Route('/', [], ['_locale' => 'de'], ['foo' => 123]));

        $loader->expects($this->once())
            ->method('load')
            ->willReturn($routeCollection);

        $delegatingLoader = new DelegatingLoader($loaderResolver, ['utf8' => true], ['_locale' => 'fr|en']);

        $loadedRouteCollection = $delegatingLoader->load('foo');
        $this->assertCount(2, $loadedRouteCollection);

        $expected = [
            'compiler_class' => RouteCompiler::class,
            'utf8' => false,
        ];
        $this->assertSame($expected, $routeCollection->get('foo')->getOptions());
        $this->assertSame(['_locale' => 'fr|en'], $routeCollection->get('foo')->getRequirements());

        $expected = [
            'compiler_class' => RouteCompiler::class,
            'foo' => 123,
            'utf8' => true,
        ];
        $this->assertSame($expected, $routeCollection->get('bar')->getOptions());
        $this->assertSame(['_locale' => 'de'], $routeCollection->get('bar')->getRequirements());
    }
}
