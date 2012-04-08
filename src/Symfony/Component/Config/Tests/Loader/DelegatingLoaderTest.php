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
use Symfony\Component\Config\Loader\DelegatingLoader;

class DelegatingLoaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Symfony\Component\Config\Loader\DelegatingLoader::__construct
     */
    public function testConstructor()
    {
        $loader = new DelegatingLoader($resolver = new LoaderResolver());
        $this->assertTrue(true, '__construct() takes a loader resolver as its first argument');
    }

    /**
     * @covers Symfony\Component\Config\Loader\DelegatingLoader::getResolver
     * @covers Symfony\Component\Config\Loader\DelegatingLoader::setResolver
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
     * @covers Symfony\Component\Config\Loader\DelegatingLoader::supports
     */
    public function testSupports()
    {
        $loader1 = $this->getMock('Symfony\Component\Config\Loader\LoaderInterface');
        $loader1->expects($this->once())->method('supports')->will($this->returnValue(true));
        $loader = new DelegatingLoader(new LoaderResolver(array($loader1)));
        $this->assertTrue($loader->supports('foo.xml'), '->supports() returns true if the resource is loadable');

        $loader1 = $this->getMock('Symfony\Component\Config\Loader\LoaderInterface');
        $loader1->expects($this->once())->method('supports')->will($this->returnValue(false));
        $loader = new DelegatingLoader(new LoaderResolver(array($loader1)));
        $this->assertFalse($loader->supports('foo.foo'), '->supports() returns false if the resource is not loadable');
    }

    /**
     * @covers Symfony\Component\Config\Loader\DelegatingLoader::load
     */
    public function testLoad()
    {
        $loader = $this->getMock('Symfony\Component\Config\Loader\LoaderInterface');
        $loader->expects($this->once())->method('supports')->will($this->returnValue(true));
        $loader->expects($this->once())->method('load');
        $resolver = new LoaderResolver(array($loader));
        $loader = new DelegatingLoader($resolver);

        $loader->load('foo');
    }

    /**
     * @expectedException Symfony\Component\Config\Exception\FileLoaderLoadException
     */
    public function testLoadThrowsAnExceptionIfTheResourceCannotBeLoaded()
    {
        $loader = $this->getMock('Symfony\Component\Config\Loader\LoaderInterface');
        $loader->expects($this->once())->method('supports')->will($this->returnValue(false));
        $resolver = new LoaderResolver(array($loader));
        $loader = new DelegatingLoader($resolver);

        $loader->load('foo');
    }
}
