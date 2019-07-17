<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Routing;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Routing\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class DelegatingLoaderTest extends TestCase
{
    public function testConstructorApi()
    {
        new DelegatingLoader(new LoaderResolver());
        $this->assertTrue(true, '__construct() takeS a LoaderResolverInterface as its first argument.');
    }

    public function testLoadDefaultOptions()
    {
        $loaderResolver = $this->getMockBuilder(LoaderResolverInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $loader = $this->getMockBuilder(LoaderInterface::class)->getMock();

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
            'compiler_class' => 'Symfony\Component\Routing\RouteCompiler',
            'utf8' => false,
        ];
        $this->assertSame($expected, $routeCollection->get('foo')->getOptions());

        $expected = [
            'compiler_class' => 'Symfony\Component\Routing\RouteCompiler',
            'foo' => 123,
            'utf8' => true,
        ];
        $this->assertSame($expected, $routeCollection->get('bar')->getOptions());
    }
}
