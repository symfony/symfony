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
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Compiler\AnalyzeServiceReferencesPass;
use Symfony\Component\DependencyInjection\Compiler\RepeatedPass;
use Symfony\Component\DependencyInjection\Compiler\InlineServiceDefinitionsPass;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;

class InlineServiceDefinitionsPassTest extends TestCase
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

    public function testProcessDoesNotInlinesWhenAliasedServiceIsShared()
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

    public function testProcessDoesInlineNonSharedService()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
            ->setShared(false)
        ;
        $container
            ->register('bar')
            ->setPublic(false)
            ->setShared(false)
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

    public function testProcessInlinesMixedServicesLoop()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
            ->addArgument(new Reference('bar'))
            ->setShared(false)
        ;
        $container
            ->register('bar')
            ->setPublic(false)
            ->addMethodCall('setFoo', array(new Reference('foo')))
        ;

        $this->process($container);

        $this->assertEquals($container->getDefinition('foo')->getArgument(0), $container->getDefinition('bar'));
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @expectedExceptionMessage Circular reference detected for service "bar", path: "bar -> foo -> bar".
     */
    public function testProcessThrowsOnNonSharedLoops()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
            ->addArgument(new Reference('bar'))
            ->setShared(false)
        ;
        $container
            ->register('bar')
            ->setShared(false)
            ->addMethodCall('setFoo', array(new Reference('foo')))
        ;

        $this->process($container);
    }

    public function testProcessNestedNonSharedServices()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
            ->addArgument(new Reference('bar1'))
            ->addArgument(new Reference('bar2'))
        ;
        $container
            ->register('bar1')
            ->setShared(false)
            ->addArgument(new Reference('baz'))
        ;
        $container
            ->register('bar2')
            ->setShared(false)
            ->addArgument(new Reference('baz'))
        ;
        $container
            ->register('baz')
            ->setShared(false)
        ;

        $this->process($container);

        $baz1 = $container->getDefinition('foo')->getArgument(0)->getArgument(0);
        $baz2 = $container->getDefinition('foo')->getArgument(1)->getArgument(0);

        $this->assertEquals($container->getDefinition('baz'), $baz1);
        $this->assertEquals($container->getDefinition('baz'), $baz2);
        $this->assertNotSame($baz1, $baz2);
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

    public function testProcessInlinesPrivateFactoryReference()
    {
        $container = new ContainerBuilder();

        $container->register('a')->setPublic(false);
        $b = $container
            ->register('b')
            ->setPublic(false)
            ->setFactory(array(new Reference('a'), 'a'))
        ;

        $container
            ->register('foo')
            ->setArguments(array(
                $ref = new Reference('b'),
            ));

        $this->process($container);

        $inlinedArguments = $container->getDefinition('foo')->getArguments();
        $this->assertSame($b, $inlinedArguments[0]);
    }

    public function testProcessDoesNotInlinePrivateFactoryIfReferencedMultipleTimesWithinTheSameDefinition()
    {
        $container = new ContainerBuilder();
        $container
            ->register('a')
        ;
        $container
            ->register('b')
            ->setPublic(false)
            ->setFactory(array(new Reference('a'), 'a'))
        ;

        $container
            ->register('foo')
            ->setArguments(array(
                    $ref1 = new Reference('b'),
                    $ref2 = new Reference('b'),
                ))
        ;
        $this->process($container);

        $args = $container->getDefinition('foo')->getArguments();
        $this->assertSame($ref1, $args[0]);
        $this->assertSame($ref2, $args[1]);
    }

    public function testProcessDoesNotInlineReferenceWhenUsedByInlineFactory()
    {
        $container = new ContainerBuilder();
        $container
            ->register('a')
        ;
        $container
            ->register('b')
            ->setPublic(false)
            ->setFactory(array(new Reference('a'), 'a'))
        ;

        $inlineFactory = new Definition();
        $inlineFactory->setPublic(false);
        $inlineFactory->setFactory(array(new Reference('b'), 'b'));

        $container
            ->register('foo')
            ->setArguments(array(
                    $ref = new Reference('b'),
                    $inlineFactory,
                ))
        ;
        $this->process($container);

        $args = $container->getDefinition('foo')->getArguments();
        $this->assertSame($ref, $args[0]);
    }

    public function testProcessDoesNotInlineWhenServiceIsPrivateButLazy()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
            ->setPublic(false)
            ->setLazy(true)
        ;

        $container
            ->register('service')
            ->setArguments(array($ref = new Reference('foo')))
        ;

        $this->process($container);

        $arguments = $container->getDefinition('service')->getArguments();
        $this->assertSame($ref, $arguments[0]);
    }

    public function testProcessDoesNotInlineWhenServiceReferencesItself()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
            ->setPublic(false)
            ->addMethodCall('foo', array($ref = new Reference('foo')))
        ;

        $this->process($container);

        $calls = $container->getDefinition('foo')->getMethodCalls();
        $this->assertSame($ref, $calls[0][1][0]);
    }

    public function testProcessDoesNotSetLazyArgumentValuesAfterInlining()
    {
        $container = new ContainerBuilder();
        $container
            ->register('inline')
            ->setShared(false)
        ;
        $container
            ->register('service-closure')
            ->setArguments(array(new ServiceClosureArgument(new Reference('inline'))))
        ;
        $container
            ->register('iterator')
            ->setArguments(array(new IteratorArgument(array(new Reference('inline')))))
        ;

        $this->process($container);

        $values = $container->getDefinition('service-closure')->getArgument(0)->getValues();
        $this->assertInstanceOf(Reference::class, $values[0]);
        $this->assertSame('inline', (string) $values[0]);

        $values = $container->getDefinition('iterator')->getArgument(0)->getValues();
        $this->assertInstanceOf(Reference::class, $values[0]);
        $this->assertSame('inline', (string) $values[0]);
    }

    /**
     * @group legacy
     */
    public function testGetInlinedServiceIdData()
    {
        $container = new ContainerBuilder();
        $container
            ->register('inlinable.service')
            ->setPublic(false)
        ;
        $container
            ->register('non_inlinable.service')
            ->setPublic(true)
        ;

        $container
            ->register('other_service')
            ->setArguments(array(new Reference('inlinable.service')))
        ;

        $inlinePass = new InlineServiceDefinitionsPass();
        $repeatedPass = new RepeatedPass(array(new AnalyzeServiceReferencesPass(), $inlinePass));
        $repeatedPass->process($container);

        $this->assertEquals(array('inlinable.service' => array('other_service')), $inlinePass->getInlinedServiceIds());
    }

    protected function process(ContainerBuilder $container)
    {
        $repeatedPass = new RepeatedPass(array(new AnalyzeServiceReferencesPass(), new InlineServiceDefinitionsPass()));
        $repeatedPass->process($container);
    }
}
