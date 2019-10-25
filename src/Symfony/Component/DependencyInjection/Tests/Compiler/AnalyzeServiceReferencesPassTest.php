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
use Symfony\Component\DependencyInjection\Compiler\RepeatedPass;
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

        $this->assertCount(4, $edges = $graph->getNode('b')->getInEdges());

        $this->assertSame($ref1, $edges[0]->getValue());
        $this->assertSame($ref4, $edges[1]->getValue());
        $this->assertSame($ref5, $edges[2]->getValue());
        $this->assertSame($ref6, $edges[3]->getValue());
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

        $this->assertCount(1, $graph->getNode('b')->getInEdges());
        $this->assertCount(1, $edges = $graph->getNode('a')->getInEdges());

        $this->assertSame($ref2, $edges[0]->getValue());
        $this->assertTrue($edges[0]->isLazy());
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

        $this->assertCount(1, $graph->getNode('a')->getInEdges());
        $this->assertCount(1, $graph->getNode('b')->getInEdges());
        $this->assertCount(2, $edges = $graph->getNode('c')->getOutEdges());

        $this->assertSame($ref1, $edges[0]->getValue());
        $this->assertFalse($edges[0]->isLazy());
        $this->assertSame($ref2, $edges[1]->getValue());
        $this->assertTrue($edges[1]->isLazy());
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

        $this->assertCount(1, $refs = $graph->getNode('a')->getInEdges());
        $this->assertSame($ref, $refs[0]->getValue());
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

        $this->assertCount(1, $refs = $graph->getNode('a')->getInEdges());
        $this->assertSame($ref, $refs[0]->getValue());
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

        $this->assertTrue($graph->hasNode('a'));
        $this->assertCount(1, $refs = $graph->getNode('a')->getInEdges());
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

        $this->assertCount(2, $graph->getNode('a')->getInEdges());
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

        $this->assertTrue($graph->hasNode('foo'));
        $this->assertCount(1, $graph->getNode('foo')->getInEdges());
    }

    protected function process(ContainerBuilder $container)
    {
        $pass = new RepeatedPass([new AnalyzeServiceReferencesPass()]);
        $pass->process($container);

        return $container->getCompiler()->getServiceReferenceGraph();
    }
}
