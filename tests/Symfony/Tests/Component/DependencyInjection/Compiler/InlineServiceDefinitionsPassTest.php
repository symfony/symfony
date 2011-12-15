<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Scope;

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Compiler\AnalyzeServiceReferencesPass;
use Symfony\Component\DependencyInjection\Compiler\Compiler;
use Symfony\Component\DependencyInjection\Compiler\RepeatedPass;
use Symfony\Component\DependencyInjection\Compiler\InlineServiceDefinitionsPass;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class InlineServiceDefinitionsPassTest extends \PHPUnit_Framework_TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container
            ->register('inlinable.service')
            ->setPublic(false)
        ;

        $container
            ->register('service')
            ->setArguments(array(new Reference('inlinable.service')))
        ;

        $this->process($container);

        $arguments = $container->getDefinition('service')->getArguments();
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Definition', $arguments[0]);
        $this->assertSame($container->getDefinition('inlinable.service'), $arguments[0]);
    }

    public function testProcessDoesNotInlineWhenAliasedServiceIsNotOfPrototypeScope()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
            ->setPublic(false)
        ;
        $container->setAlias('moo', 'foo');

        $container
            ->register('service')
            ->setArguments(array($ref = new Reference('foo')))
        ;

        $this->process($container);

        $arguments = $container->getDefinition('service')->getArguments();
        $this->assertSame($ref, $arguments[0]);
    }

    public function testProcessDoesInlineServiceOfPrototypeScope()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
            ->setScope('prototype')
        ;
        $container
            ->register('bar')
            ->setPublic(false)
            ->setScope('prototype')
        ;
        $container->setAlias('moo', 'bar');

        $container
            ->register('service')
            ->setArguments(array(new Reference('foo'), $ref = new Reference('moo'), new Reference('bar')))
        ;

        $this->process($container);

        $arguments = $container->getDefinition('service')->getArguments();
        $this->assertEquals($container->getDefinition('foo'), $arguments[0]);
        $this->assertNotSame($container->getDefinition('foo'), $arguments[0]);
        $this->assertSame($ref, $arguments[1]);
        $this->assertEquals($container->getDefinition('bar'), $arguments[2]);
        $this->assertNotSame($container->getDefinition('bar'), $arguments[2]);
    }

    public function testProcessInlinesIfMultipleReferencesButAllFromTheSameDefinition()
    {
        $container = new ContainerBuilder();

        $a = $container->register('a')->setPublic(false);
        $b = $container
            ->register('b')
            ->addArgument(new Reference('a'))
            ->addArgument(new Definition(null, array(new Reference('a'))))
        ;

        $this->process($container);

        $arguments = $b->getArguments();
        $this->assertSame($a, $arguments[0]);

        $inlinedArguments = $arguments[1]->getArguments();
        $this->assertSame($a, $inlinedArguments[0]);
    }

    public function testProcessInlinesOnlyIfSameScope()
    {
        $container = new ContainerBuilder();

        $container->addScope(new Scope('foo'));
        $a = $container->register('a')->setPublic(false)->setScope('foo');
        $b = $container->register('b')->addArgument(new Reference('a'));

        $this->process($container);
        $arguments = $b->getArguments();
        $this->assertEquals(new Reference('a'), $arguments[0]);
        $this->assertTrue($container->hasDefinition('a'));
    }

    protected function process(ContainerBuilder $container)
    {
        $repeatedPass = new RepeatedPass(array(new AnalyzeServiceReferencesPass(), new InlineServiceDefinitionsPass()));
        $repeatedPass->process($container);
    }
}
