<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\ResolveParameterPlaceHoldersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ResolveParameterPlaceHoldersPassTest extends TestCase
{
    private $compilerPass;
    private $container;
    private $fooDefinition;

    protected function setUp()
    {
        $this->compilerPass = new ResolveParameterPlaceHoldersPass();
        $this->container = $this->createContainerBuilder();
        $this->compilerPass->process($this->container);
        $this->fooDefinition = $this->container->getDefinition('foo');
    }

    public function testClassParametersShouldBeResolved()
    {
        $this->assertSame('Foo', $this->fooDefinition->getClass());
    }

    public function testFactoryParametersShouldBeResolved()
    {
        $this->assertSame(array('FooFactory', 'getFoo'), $this->fooDefinition->getFactory());
    }

    public function testArgumentParametersShouldBeResolved()
    {
        $this->assertSame(array('bar', 'baz'), $this->fooDefinition->getArguments());
    }

    public function testMethodCallParametersShouldBeResolved()
    {
        $this->assertSame(array(array('foobar', array('bar', 'baz'))), $this->fooDefinition->getMethodCalls());
    }

    public function testPropertyParametersShouldBeResolved()
    {
        $this->assertSame(array('bar' => 'baz'), $this->fooDefinition->getProperties());
    }

    public function testFileParametersShouldBeResolved()
    {
        $this->assertSame('foo.php', $this->fooDefinition->getFile());
    }

    public function testAliasParametersShouldBeResolved()
    {
        $this->assertSame('foo', $this->container->getAlias('bar')->__toString());
    }

    private function createContainerBuilder()
    {
        $containerBuilder = new ContainerBuilder();

        $containerBuilder->setParameter('foo.class', 'Foo');
        $containerBuilder->setParameter('foo.factory.class', 'FooFactory');
        $containerBuilder->setParameter('foo.arg1', 'bar');
        $containerBuilder->setParameter('foo.arg2', 'baz');
        $containerBuilder->setParameter('foo.method', 'foobar');
        $containerBuilder->setParameter('foo.property.name', 'bar');
        $containerBuilder->setParameter('foo.property.value', 'baz');
        $containerBuilder->setParameter('foo.file', 'foo.php');
        $containerBuilder->setParameter('alias.id', 'bar');

        $fooDefinition = $containerBuilder->register('foo', '%foo.class%');
        $fooDefinition->setFactory(array('%foo.factory.class%', 'getFoo'));
        $fooDefinition->setArguments(array('%foo.arg1%', '%foo.arg2%'));
        $fooDefinition->addMethodCall('%foo.method%', array('%foo.arg1%', '%foo.arg2%'));
        $fooDefinition->setProperty('%foo.property.name%', '%foo.property.value%');
        $fooDefinition->setFile('%foo.file%');

        $containerBuilder->setAlias('%alias.id%', 'foo');

        return $containerBuilder;
    }
}
