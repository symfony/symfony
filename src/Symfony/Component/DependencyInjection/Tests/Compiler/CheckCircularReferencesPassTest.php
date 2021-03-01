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
use Symfony\Component\DependencyInjection\Compiler\CheckCircularReferencesPass;
use Symfony\Component\DependencyInjection\Compiler\Compiler;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Reference;

class CheckCircularReferencesPassTest extends TestCase
{
    public function testProcess()
    {
        $this->expectException(ServiceCircularReferenceException::class);
        $container = new ContainerBuilder();
        $container->register('a')->addArgument(new Reference('b'));
        $container->register('b')->addArgument(new Reference('a'));

        $this->process($container);
    }

    public function testProcessWithAliases()
    {
        $this->expectException(ServiceCircularReferenceException::class);
        $container = new ContainerBuilder();
        $container->register('a')->addArgument(new Reference('b'));
        $container->setAlias('b', 'c');
        $container->setAlias('c', 'a');

        $this->process($container);
    }

    public function testProcessWithFactory()
    {
        $this->expectException(ServiceCircularReferenceException::class);
        $container = new ContainerBuilder();

        $container
            ->register('a', 'stdClass')
            ->setFactory([new Reference('b'), 'getInstance']);

        $container
            ->register('b', 'stdClass')
            ->setFactory([new Reference('a'), 'getInstance']);

        $this->process($container);
    }

    public function testProcessDetectsIndirectCircularReference()
    {
        $this->expectException(ServiceCircularReferenceException::class);
        $container = new ContainerBuilder();
        $container->register('a')->addArgument(new Reference('b'));
        $container->register('b')->addArgument(new Reference('c'));
        $container->register('c')->addArgument(new Reference('a'));

        $this->process($container);
    }

    public function testProcessDetectsIndirectCircularReferenceWithFactory()
    {
        $this->expectException(ServiceCircularReferenceException::class);
        $container = new ContainerBuilder();

        $container->register('a')->addArgument(new Reference('b'));

        $container
            ->register('b', 'stdClass')
            ->setFactory([new Reference('c'), 'getInstance']);

        $container->register('c')->addArgument(new Reference('a'));

        $this->process($container);
    }

    public function testDeepCircularReference()
    {
        $this->expectException(ServiceCircularReferenceException::class);
        $container = new ContainerBuilder();
        $container->register('a')->addArgument(new Reference('b'));
        $container->register('b')->addArgument(new Reference('c'));
        $container->register('c')->addArgument(new Reference('b'));

        $this->process($container);
    }

    public function testProcessIgnoresMethodCalls()
    {
        $container = new ContainerBuilder();
        $container->register('a')->addArgument(new Reference('b'));
        $container->register('b')->addMethodCall('setA', [new Reference('a')]);

        $this->process($container);

        $this->addToAssertionCount(1);
    }

    public function testProcessIgnoresLazyServices()
    {
        $container = new ContainerBuilder();
        $container->register('a')->setLazy(true)->addArgument(new Reference('b'));
        $container->register('b')->addArgument(new Reference('a'));

        $this->process($container);

        // just make sure that a lazily loaded service does not trigger a CircularReferenceException
        $this->addToAssertionCount(1);
    }

    public function testProcessIgnoresIteratorArguments()
    {
        $container = new ContainerBuilder();
        $container->register('a')->addArgument(new Reference('b'));
        $container->register('b')->addArgument(new IteratorArgument([new Reference('a')]));

        $this->process($container);

        // just make sure that an IteratorArgument does not trigger a CircularReferenceException
        $this->addToAssertionCount(1);
    }

    protected function process(ContainerBuilder $container)
    {
        $compiler = new Compiler();
        $passConfig = $compiler->getPassConfig();
        $passConfig->setOptimizationPasses([
            new AnalyzeServiceReferencesPass(true),
            new CheckCircularReferencesPass(),
        ]);
        $passConfig->setRemovingPasses([]);
        $passConfig->setAfterRemovingPasses([]);

        $compiler->compile($container);
    }
}
