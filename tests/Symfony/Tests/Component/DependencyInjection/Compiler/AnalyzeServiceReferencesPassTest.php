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

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Compiler\Compiler;
use Symfony\Component\DependencyInjection\Compiler\AnalyzeServiceReferencesPass;
use Symfony\Component\DependencyInjection\Compiler\RepeatedPass;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AnalyzeServiceReferencesPassTest extends \PHPUnit_Framework_TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();

        $a = $container
            ->register('a')
            ->addArgument($ref1 = new Reference('b'))
        ;

        $b = $container
            ->register('b')
            ->addMethodCall('setA', array($ref2 = new Reference('a')))
        ;

        $c = $container
            ->register('c')
            ->addArgument($ref3 = new Reference('a'))
            ->addArgument($ref4 = new Reference('b'))
        ;

        $d = $container
            ->register('d')
            ->setProperty('foo', $ref5 = new Reference('b'))
        ;

        $graph = $this->process($container);

        $this->assertEquals(3, count($edges = $graph->getNode('b')->getInEdges()));
        $this->assertSame($ref1, $edges[0]->getValue());
        $this->assertSame($ref4, $edges[1]->getValue());
        $this->assertSame($ref5, $edges[2]->getValue());
    }

    public function testProcessDetectsReferencesFromInlinedDefinitions()
    {
        $container = new ContainerBuilder();

        $container
            ->register('a')
        ;

        $container
            ->register('b')
            ->addArgument(new Definition(null, array($ref = new Reference('a'))))
        ;

        $graph = $this->process($container);

        $this->assertEquals(1, count($refs = $graph->getNode('a')->getInEdges()));
        $this->assertSame($ref, $refs[0]->getValue());
    }

    public function testProcessDoesNotSaveDuplicateReferences()
    {
        $container = new ContainerBuilder();

        $container
            ->register('a')
        ;
        $container
            ->register('b')
            ->addArgument(new Definition(null, array($ref1 = new Reference('a'))))
            ->addArgument(new Definition(null, array($ref2 = new Reference('a'))))
        ;

        $graph = $this->process($container);

        $this->assertEquals(2, count($graph->getNode('a')->getInEdges()));
    }

    protected function process(ContainerBuilder $container)
    {
        $pass = new RepeatedPass(array(new AnalyzeServiceReferencesPass()));
        $pass->process($container);

        return $container->getCompiler()->getServiceReferenceGraph();
    }
}
