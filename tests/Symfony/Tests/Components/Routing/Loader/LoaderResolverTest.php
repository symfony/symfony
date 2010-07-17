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
use Symfony\Components\Routing\Loader\ClosureLoader;

class LoaderResolverTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Symfony\Components\Routing\Loader\LoaderResolver::__construct
     */
    public function testConstructor()
    {
        $resolver = new LoaderResolver(array(
            $loader = new ClosureLoader(),
        ));

        $this->assertEquals(array($loader), $resolver->getLoaders(), '__construct() takes an array of loaders as its first argument');
    }

    /**
     * @covers Symfony\Components\Routing\Loader\LoaderResolver::resolve
     */
    public function testResolve()
    {
        $resolver = new LoaderResolver(array(
            $loader = new ClosureLoader(),
        ));

        $this->assertFalse($resolver->resolve('foo.foo'), '->resolve() returns false if no loader is able to load the resource');
        $this->assertEquals($loader, $resolver->resolve(function () {}), '->resolve() returns the loader for the given resource');
    }

    /**
     * @covers Symfony\Components\Routing\Loader\LoaderResolver::getLoaders
     * @covers Symfony\Components\Routing\Loader\LoaderResolver::addLoader
     */
    public function testLoaders()
    {
        $resolver = new LoaderResolver();
        $resolver->addLoader($loader = new ClosureLoader());

        $this->assertEquals(array($loader), $resolver->getLoaders(), 'addLoader() adds a loader');
    }
}
