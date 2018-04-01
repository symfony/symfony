<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\Tests\Compiler;

use PHPUnit\Framework\TestCase;
use Symphony\Component\DependencyInjection\Alias;
use Symphony\Component\DependencyInjection\Definition;
use Symphony\Component\DependencyInjection\Reference;
use Symphony\Component\DependencyInjection\Compiler\ResolveReferencesToAliasesPass;
use Symphony\Component\DependencyInjection\ContainerBuilder;

class ResolveReferencesToAliasesPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->setAlias('bar', 'foo');
        $def = $container
            ->register('moo')
            ->setArguments(array(new Reference('bar')))
        ;

        $this->process($container);

        $arguments = $def->getArguments();
        $this->assertEquals('foo', (string) $arguments[0]);
    }

    public function testProcessRecursively()
    {
        $container = new ContainerBuilder();
        $container->setAlias('bar', 'foo');
        $container->setAlias('moo', 'bar');
        $def = $container
            ->register('foobar')
            ->setArguments(array(new Reference('moo')))
        ;

        $this->process($container);

        $arguments = $def->getArguments();
        $this->assertEquals('foo', (string) $arguments[0]);
    }

    /**
     * @expectedException \Symphony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     */
    public function testAliasCircularReference()
    {
        $container = new ContainerBuilder();
        $container->setAlias('bar', 'foo');
        $container->setAlias('foo', 'bar');
        $this->process($container);
    }

    public function testResolveFactory()
    {
        $container = new ContainerBuilder();
        $container->register('factory', 'Factory');
        $container->setAlias('factory_alias', new Alias('factory'));
        $foo = new Definition();
        $foo->setFactory(array(new Reference('factory_alias'), 'createFoo'));
        $container->setDefinition('foo', $foo);
        $bar = new Definition();
        $bar->setFactory(array('Factory', 'createFoo'));
        $container->setDefinition('bar', $bar);

        $this->process($container);

        $resolvedFooFactory = $container->getDefinition('foo')->getFactory();
        $resolvedBarFactory = $container->getDefinition('bar')->getFactory();

        $this->assertSame('factory', (string) $resolvedFooFactory[0]);
        $this->assertSame('Factory', (string) $resolvedBarFactory[0]);
    }

    protected function process(ContainerBuilder $container)
    {
        $pass = new ResolveReferencesToAliasesPass();
        $pass->process($container);
    }
}
