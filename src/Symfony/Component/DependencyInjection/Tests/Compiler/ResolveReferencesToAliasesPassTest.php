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
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Compiler\ResolveReferencesToAliasesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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
     * @expectedException \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
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

    /**
     * @group legacy
     */
    public function testResolveFactoryService()
    {
        $container = new ContainerBuilder();
        $container->register('factory', 'Factory');
        $container->setAlias('factory_alias', new Alias('factory'));
        $foo = new Definition();
        $foo->setFactoryService('factory_alias');
        $foo->setFactoryMethod('createFoo');
        $container->setDefinition('foo', $foo);

        $this->process($container);

        $this->assertSame('factory', $foo->getFactoryService());
    }

    /**
     * @dataProvider getAliasVisibility
     */
    public function testResolveNestedDefinitionWithAliasReferences($public)
    {
        $container = new ContainerBuilder();

        $container->register('original_dep');
        $container->setAlias('dependency', new Alias('original_dep', $public));

        $innerDefinition = new Definition();
        $innerDefinition->setArguments(array(new Reference('dependency')));

        $container
            ->register('service')
            ->setArguments(array($innerDefinition));

        $this->process($container);

        $this->assertSame('original_dep', (string) $container->getDefinition('service')->getArgument(0)->getArgument(0));
    }

    public function getAliasVisibility()
    {
        return array(
            'private' => array(false),
            'public' => array(true),
        );
    }

    public function testResolveNestedDefinitionWithDirectServiceReferences()
    {
        $container = new ContainerBuilder();

        $container->register('original_dep');

        $innerDefinition = new Definition();
        $innerDefinition->setArguments(array(new Reference('original_dep')));

        $container
            ->register('service')
            ->setArguments(array($innerDefinition));

        $this->process($container);

        $this->assertSame('original_dep', (string) $container->getDefinition('service')->getArgument(0)->getArgument(0));
    }

    protected function process(ContainerBuilder $container)
    {
        $pass = new ResolveReferencesToAliasesPass();
        $pass->process($container);
    }
}
