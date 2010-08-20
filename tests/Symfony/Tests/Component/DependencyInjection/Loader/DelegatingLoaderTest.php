<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\DependencyInjection\Loader;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\Loader\DelegatingLoader;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;

class DelegatingLoaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Symfony\Component\DependencyInjection\Loader\DelegatingLoader::__construct
     */
    public function testConstructor()
    {
        $resolver = new LoaderResolver();
        $loader = new DelegatingLoader($resolver);
        $this->assertTrue(true, '__construct() takes a loader resolver as its first argument');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\Loader\DelegatingLoader::getResolver
     * @covers Symfony\Component\DependencyInjection\Loader\DelegatingLoader::setResolver
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
     * @covers Symfony\Component\DependencyInjection\Loader\DelegatingLoader::supports
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
     * @covers Symfony\Component\DependencyInjection\Loader\DelegatingLoader::load
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
