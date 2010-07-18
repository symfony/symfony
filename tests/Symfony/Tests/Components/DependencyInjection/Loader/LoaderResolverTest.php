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
use Symfony\Components\DependencyInjection\Loader\ClosureLoader;

class LoaderResolverTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Symfony\Components\DependencyInjection\Loader\LoaderResolver::__construct
     */
    public function testConstructor()
    {
        $resolver = new LoaderResolver(array(
            $loader = new ClosureLoader(new ContainerBuilder()),
        ));

        $this->assertEquals(array($loader), $resolver->getLoaders(), '__construct() takes an array of loaders as its first argument');
    }

    /**
     * @covers Symfony\Components\DependencyInjection\Loader\LoaderResolver::resolve
     */
    public function testResolve()
    {
        $resolver = new LoaderResolver(array(
            $loader = new ClosureLoader(new ContainerBuilder()),
        ));

        $this->assertFalse($resolver->resolve('foo.foo'), '->resolve() returns false if no loader is able to load the resource');
        $this->assertEquals($loader, $resolver->resolve(function () {}), '->resolve() returns the loader for the given resource');
    }

    /**
     * @covers Symfony\Components\DependencyInjection\Loader\LoaderResolver::getLoaders
     * @covers Symfony\Components\DependencyInjection\Loader\LoaderResolver::addLoader
     */
    public function testLoaders()
    {
        $resolver = new LoaderResolver();
        $resolver->addLoader($loader = new ClosureLoader(new ContainerBuilder()));

        $this->assertEquals(array($loader), $resolver->getLoaders(), 'addLoader() adds a loader');
    }
}
