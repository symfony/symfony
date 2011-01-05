<?php

namespace Symfony\Tests\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\RemoveUnusedDefinitionsPass;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class RemoveUnusedDefinitionsPassTest extends \PHPUnit_Framework_TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
            ->setPublic(false)
        ;
        $container
            ->register('bar')
            ->setPublic(false)
        ;
        $container
            ->register('moo')
            ->setArguments(array(new Reference('bar')))
        ;

        $this->process($container);

        $this->assertFalse($container->hasDefinition('foo'));
        $this->assertTrue($container->hasDefinition('bar'));
        $this->assertTrue($container->hasDefinition('moo'));
    }

    public function testProcessRemovesUnusedDefinitionsRecursively()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
            ->setPublic(false)
        ;
        $container
            ->register('bar')
            ->setArguments(array(new Reference('foo')))
            ->setPublic(false)
        ;

        $this->process($container);

        $this->assertFalse($container->hasDefinition('foo'));
        $this->assertFalse($container->hasDefinition('bar'));
    }

    public function testProcessWorksWithInlinedDefinitions()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
            ->setPublic(false)
        ;
        $container
            ->register('bar')
            ->setArguments(array(new Definition(null, array(new Reference('foo')))))
        ;

        $this->process($container);

        $this->assertTrue($container->hasDefinition('foo'));
        $this->assertTrue($container->hasDefinition('bar'));
    }

    protected function process(ContainerBuilder $container)
    {
        $pass = new RemoveUnusedDefinitionsPass();
        $pass->process($container);
    }
}