<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Loader;

use Symfony\Component\Config\Loader\LoaderResolver;

class LoaderResolverTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Symfony\Component\Config\Loader\LoaderResolver::__construct
     */
    public function testConstructor()
    {
        $resolver = new LoaderResolver(array(
            $loader = $this->getMock('Symfony\Component\Config\Loader\LoaderInterface'),
        ));

        $this->assertEquals(array($loader), $resolver->getLoaders(), '__construct() takes an array of loaders as its first argument');
    }

    /**
     * @covers Symfony\Component\Config\Loader\LoaderResolver::resolve
     */
    public function testResolve()
    {
        $loader = $this->getMock('Symfony\Component\Config\Loader\LoaderInterface');
        $resolver = new LoaderResolver(array($loader));
        $this->assertFalse($resolver->resolve('foo.foo'), '->resolve() returns false if no loader is able to load the resource');

        $loader = $this->getMock('Symfony\Component\Config\Loader\LoaderInterface');
        $loader->expects($this->once())->method('supports')->will($this->returnValue(true));
        $resolver = new LoaderResolver(array($loader));
        $this->assertEquals($loader, $resolver->resolve(function () {}), '->resolve() returns the loader for the given resource');
    }

    /**
     * @covers Symfony\Component\Config\Loader\LoaderResolver::getLoaders
     * @covers Symfony\Component\Config\Loader\LoaderResolver::addLoader
     */
    public function testLoaders()
    {
        $resolver = new LoaderResolver();
        $resolver->addLoader($loader = $this->getMock('Symfony\Component\Config\Loader\LoaderInterface'));

        $this->assertEquals(array($loader), $resolver->getLoaders(), 'addLoader() adds a loader');
    }
}
