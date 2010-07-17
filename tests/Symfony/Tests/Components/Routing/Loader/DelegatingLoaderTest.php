<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Routing\Loader;

use Symfony\Components\Routing\Loader\LoaderResolver;
use Symfony\Components\Routing\Loader\DelegatingLoader;
use Symfony\Components\Routing\Loader\XmlFileLoader;
use Symfony\Components\Routing\Loader\ClosureLoader;
use Symfony\Components\Routing\RouteCollection;
use Symfony\Components\Routing\Route;

class DelegatingLoaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Symfony\Components\Routing\Loader\DelegatingLoader::__construct
     */
    public function testConstructor()
    {
        $resolver = new LoaderResolver();
        $loader = new DelegatingLoader($resolver);
        $this->assertTrue(true, '__construct() takes a loader resolver as its first argument');
    }

    /**
     * @covers Symfony\Components\Routing\Loader\DelegatingLoader::getResolver
     * @covers Symfony\Components\Routing\Loader\DelegatingLoader::setResolver
     */
    public function testGetSetResolver()
    {
        $resolver = new LoaderResolver();
        $loader = new DelegatingLoader($resolver);
        $this->assertSame($resolver, $loader->getResolver(), '->getResolver() gets the resolver loader');
        $loader->setResolver($resolver = new LoaderResolver());
        $this->assertSame($resolver, $loader->getResolver(), '->setResolver() sets the resolver loader');
    }

    /**
     * @covers Symfony\Components\Routing\Loader\DelegatingLoader::supports
     */
    public function testSupports()
    {
        $resolver = new LoaderResolver(array(
            $ini = new XmlFileLoader(array()),
        ));
        $loader = new DelegatingLoader($resolver);

        $this->assertTrue($loader->supports('foo.xml'), '->supports() returns true if the resource is loadable');
        $this->assertFalse($loader->supports('foo.foo'), '->supports() returns true if the resource is loadable');
    }

    /**
     * @covers Symfony\Components\Routing\Loader\DelegatingLoader::load
     */
    public function testLoad()
    {
        $resolver = new LoaderResolver(array(
            new ClosureLoader(),
        ));
        $loader = new DelegatingLoader($resolver);

        $route = new Route('/');
        $routes = $loader->load(function () use ($route)
        {
            $routes = new RouteCollection();

            $routes->addRoute('foo', $route);

            return $routes;
        });

        $this->assertSame($route, $routes->getRoute('foo'), '->load() loads a resource using the loaders from the resolver');

        try {
            $loader->load('foo.foo');
            $this->fail('->load() throws an \InvalidArgumentException if the resource cannot be loaded');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->load() throws an \InvalidArgumentException if the resource cannot be loaded');
        }
    }
}
