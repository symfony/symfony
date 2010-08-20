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
use Symfony\Component\DependencyInjection\Loader\Loader;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;

class LoaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Symfony\Component\DependencyInjection\Loader\Loader::__construct
     */
    public function testConstructor()
    {
        $loader = new ProjectLoader1(new ContainerBuilder());
        $this->assertTrue(true, '__construct() takes a container builder as its first argument');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\Loader\Loader::getResolver
     * @covers Symfony\Component\DependencyInjection\Loader\Loader::setResolver
     */
    public function testGetSetResolver()
    {
        $resolver = new LoaderResolver();
        $loader = new ProjectLoader1(new ContainerBuilder());
        $loader->setResolver($resolver);
        $this->assertSame($resolver, $loader->getResolver(), '->setResolver() sets the resolver loader');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\Loader\Loader::resolve
     */
    public function testResolve()
    {
        $container = new ContainerBuilder();

        $resolver = new LoaderResolver(array(
            $ini = new IniFileLoader($container, array()),
        ));
        $loader = new ProjectLoader1($container);
        $loader->setResolver($resolver);

        $this->assertSame($ini, $loader->resolve('foo.ini'), '->resolve() finds a loader');
        $this->assertSame($loader, $loader->resolve('foo.foo'), '->resolve() finds a loader');

        try {
            $loader->resolve(new \stdClass());
            $this->fail('->resolve() throws an \InvalidArgumentException if the resource cannot be loaded');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->resolve() throws an \InvalidArgumentException if the resource cannot be loaded');
        }
    }
}

class ProjectLoader1 extends Loader
{
    public function load($resource)
    {
    }

    public function supports($resource)
    {
        return is_string($resource) && 'foo' === pathinfo($resource, PATHINFO_EXTENSION);
    }
}
