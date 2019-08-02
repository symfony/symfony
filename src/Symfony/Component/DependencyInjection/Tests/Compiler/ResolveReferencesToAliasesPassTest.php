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
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Compiler\ResolveReferencesToAliasesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class ResolveReferencesToAliasesPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->setAlias('bar', 'foo');
        $def = $container
            ->register('moo')
            ->setArguments([new Reference('bar')])
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
            ->setArguments([new Reference('moo')])
        ;

        $this->process($container);

        $arguments = $def->getArguments();
        $this->assertEquals('foo', (string) $arguments[0]);
    }

    public function testAliasCircularReference()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException');
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
        $foo->setFactory([new Reference('factory_alias'), 'createFoo']);
        $container->setDefinition('foo', $foo);
        $bar = new Definition();
        $bar->setFactory(['Factory', 'createFoo']);
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
