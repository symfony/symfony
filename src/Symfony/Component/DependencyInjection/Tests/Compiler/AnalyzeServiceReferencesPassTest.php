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
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\AnalyzeServiceReferencesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class AnalyzeServiceReferencesPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();

        $container
            ->register('a')
            ->addArgument($ref1 = new Reference('b'))
        ;

        $container
            ->register('b')
            ->addMethodCall('setA', [$ref2 = new Reference('a')])
        ;

        $container
            ->register('c')
            ->addArgument($ref3 = new Reference('a'))
            ->addArgument($ref4 = new Reference('b'))
        ;

        $container
            ->register('d')
            ->setProperty('foo', $ref5 = new Reference('b'))
        ;

        $container
            ->register('e')
            ->setConfigurator([$ref6 = new Reference('b'), 'methodName'])
        ;

        $graph = $this->process($container);

        self::assertCount(4, $edges = $graph->getNode('b')->getInEdges());

        self::assertSame($ref1, $edges[0]->getValue());
        self::assertSame($ref4, $edges[1]->getValue());
        self::assertSame($ref5, $edges[2]->getValue());
        self::assertSame($ref6, $edges[3]->getValue());
    }

    public function testProcessMarksEdgesLazyWhenReferencedServiceIsLazy()
    {
        $container = new ContainerBuilder();

        $container
            ->register('a')
            ->setLazy(true)
            ->addArgument($ref1 = new Reference('b'))
        ;

        $container
            ->register('b')
            ->addArgument($ref2 = new Reference('a'))
        ;

        $graph = $this->process($container);

        self::assertCount(1, $graph->getNode('b')->getInEdges());
        self::assertCount(1, $edges = $graph->getNode('a')->getInEdges());

        self::assertSame($ref2, $edges[0]->getValue());
        self::assertTrue($edges[0]->isLazy());
    }

    public function testProcessMarksEdgesLazyWhenReferencedFromIteratorArgument()
    {
        $container = new ContainerBuilder();
        $container->register('a');
        $container->register('b');

        $container
            ->register('c')
            ->addArgument($ref1 = new Reference('a'))
            ->addArgument(new IteratorArgument([$ref2 = new Reference('b')]))
        ;

        $graph = $this->process($container);

        self::assertCount(1, $graph->getNode('a')->getInEdges());
        self::assertCount(1, $graph->getNode('b')->getInEdges());
        self::assertCount(2, $edges = $graph->getNode('c')->getOutEdges());

        self::assertSame($ref1, $edges[0]->getValue());
        self::assertFalse($edges[0]->isLazy());
        self::assertSame($ref2, $edges[1]->getValue());
        self::assertTrue($edges[1]->isLazy());
    }

    public function testProcessDetectsReferencesFromInlinedDefinitions()
    {
        $container = new ContainerBuilder();

        $container
            ->register('a')
        ;

        $container
            ->register('b')
            ->addArgument(new Definition(null, [$ref = new Reference('a')]))
        ;

        $graph = $this->process($container);

        self::assertCount(1, $refs = $graph->getNode('a')->getInEdges());
        self::assertSame($ref, $refs[0]->getValue());
    }

    public function testProcessDetectsReferencesFromIteratorArguments()
    {
        $container = new ContainerBuilder();

        $container
            ->register('a')
        ;

        $container
            ->register('b')
            ->addArgument(new IteratorArgument([$ref = new Reference('a')]))
        ;

        $graph = $this->process($container);

        self::assertCount(1, $refs = $graph->getNode('a')->getInEdges());
        self::assertSame($ref, $refs[0]->getValue());
    }

    public function testProcessDetectsReferencesFromInlinedFactoryDefinitions()
    {
        $container = new ContainerBuilder();

        $container
            ->register('a')
        ;

        $factory = new Definition();
        $factory->setFactory([new Reference('a'), 'a']);

        $container
            ->register('b')
            ->addArgument($factory)
        ;

        $graph = $this->process($container);

        self::assertTrue($graph->hasNode('a'));
        self::assertCount(1, $refs = $graph->getNode('a')->getInEdges());
    }

    public function testProcessDoesNotSaveDuplicateReferences()
    {
        $container = new ContainerBuilder();

        $container
            ->register('a')
        ;
        $container
            ->register('b')
            ->addArgument(new Definition(null, [$ref1 = new Reference('a')]))
            ->addArgument(new Definition(null, [$ref2 = new Reference('a')]))
        ;

        $graph = $this->process($container);

        self::assertCount(2, $graph->getNode('a')->getInEdges());
    }

    public function testProcessDetectsFactoryReferences()
    {
        $container = new ContainerBuilder();

        $container
            ->register('foo', 'stdClass')
            ->setFactory(['stdClass', 'getInstance']);

        $container
            ->register('bar', 'stdClass')
            ->setFactory([new Reference('foo'), 'getInstance']);

        $graph = $this->process($container);

        self::assertTrue($graph->hasNode('foo'));
        self::assertCount(1, $graph->getNode('foo')->getInEdges());
    }

    protected function process(ContainerBuilder $container)
    {
        $pass = new AnalyzeServiceReferencesPass();
        $pass->process($container);

        return $container->getCompiler()->getServiceReferenceGraph();
    }
}
