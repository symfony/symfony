<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\DependencyInjection\Loader;

use Symfony\Components\DependencyInjection\ContainerBuilder;
use Symfony\Components\DependencyInjection\Loader\LoaderResolver;
use Symfony\Components\DependencyInjection\Loader\DelegatingLoader;
use Symfony\Components\DependencyInjection\Loader\IniFileLoader;
use Symfony\Components\DependencyInjection\Loader\ClosureLoader;

class DelegatingLoaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Symfony\Components\DependencyInjection\Loader\DelegatingLoader::__construct
     */
    public function testConstructor()
    {
        $resolver = new LoaderResolver();
        $loader = new DelegatingLoader($resolver);
        $this->assertTrue(true, '__construct() takes a loader resolver as its first argument');
    }

    /**
     * @covers Symfony\Components\DependencyInjection\Loader\DelegatingLoader::getResolver
     * @covers Symfony\Components\DependencyInjection\Loader\DelegatingLoader::setResolver
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
     * @covers Symfony\Components\DependencyInjection\Loader\DelegatingLoader::supports
     */
    public function testSupports()
    {
        $container = new ContainerBuilder();
        $resolver = new LoaderResolver(array(
            $ini = new IniFileLoader($container, array()),
        ));
        $loader = new DelegatingLoader($resolver);

        $this->assertTrue($loader->supports('foo.ini'), '->supports() returns true if the resource is loadable');
        $this->assertFalse($loader->supports('foo.foo'), '->supports() returns true if the resource is loadable');
    }

    /**
     * @covers Symfony\Components\DependencyInjection\Loader\DelegatingLoader::load
     */
    public function testLoad()
    {
        $container = new ContainerBuilder();
        $resolver = new LoaderResolver(array(
            new ClosureLoader($container),
        ));
        $loader = new DelegatingLoader($resolver);

        $loader->load(function ($container)
        {
            $container->setParameter('foo', 'foo');
        });

        $this->assertEquals('foo', $container->getParameter('foo'), '->load() loads a resource using the loaders from the resolver');

        try {
            $loader->load('foo.foo');
            $this->fail('->load() throws an \InvalidArgumentException if the resource cannot be loaded');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->load() throws an \InvalidArgumentException if the resource cannot be loaded');
        }
    }
}
