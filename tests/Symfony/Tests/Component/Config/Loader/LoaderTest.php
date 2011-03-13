<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Config\Loader;

use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Config\Loader\Loader;

class LoaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Symfony\Component\Config\Loader\Loader::getResolver
     * @covers Symfony\Component\Config\Loader\Loader::setResolver
     */
    public function testGetSetResolver()
    {
        $resolver = new LoaderResolver();
        $loader = new ProjectLoader1();
        $loader->setResolver($resolver);
        $this->assertSame($resolver, $loader->getResolver(), '->setResolver() sets the resolver loader');
    }

    /**
     * @covers Symfony\Component\Config\Loader\Loader::resolve
     */
    public function testResolve()
    {
        $loader1 = $this->getMock('Symfony\Component\Config\Loader\LoaderInterface');
        $loader1->expects($this->once())->method('supports')->will($this->returnValue(true));
        $resolver = new LoaderResolver(array($loader1));
        $loader = new ProjectLoader1();
        $loader->setResolver($resolver);

        $this->assertSame($loader, $loader->resolve('foo.foo'), '->resolve() finds a loader');
        $this->assertSame($loader1, $loader->resolve('foo.xml'), '->resolve() finds a loader');

        $loader1 = $this->getMock('Symfony\Component\Config\Loader\LoaderInterface');
        $loader1->expects($this->once())->method('supports')->will($this->returnValue(false));
        $resolver = new LoaderResolver(array($loader1));
        $loader = new ProjectLoader1();
        $loader->setResolver($resolver);
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
    public function load($resource, $type = null)
    {
    }

    public function supports($resource, $type = null)
    {
        return is_string($resource) && 'foo' === pathinfo($resource, PATHINFO_EXTENSION);
    }

    public function getType()
    {
    }
}
