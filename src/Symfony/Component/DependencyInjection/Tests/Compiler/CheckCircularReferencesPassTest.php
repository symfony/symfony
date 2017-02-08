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
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Compiler\CheckCircularReferencesPass;
use Symfony\Component\DependencyInjection\Compiler\AnalyzeServiceReferencesPass;
use Symfony\Component\DependencyInjection\Compiler\Compiler;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CheckCircularReferencesPassTest extends TestCase
{
    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     */
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->register('a')->addArgument(new Reference('b'));
        $container->register('b')->addArgument(new Reference('a'));

        $this->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     */
    public function testProcessWithAliases()
    {
        $container = new ContainerBuilder();
        $container->register('a')->addArgument(new Reference('b'));
        $container->setAlias('b', 'c');
        $container->setAlias('c', 'a');

        $this->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     */
    public function testProcessWithFactory()
    {
        $container = new ContainerBuilder();

        $container
            ->register('a', 'stdClass')
            ->setFactory(array(new Reference('b'), 'getInstance'));

        $container
            ->register('b', 'stdClass')
            ->setFactory(array(new Reference('a'), 'getInstance'));

        $this->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     */
    public function testProcessDetectsIndirectCircularReference()
    {
        $container = new ContainerBuilder();
        $container->register('a')->addArgument(new Reference('b'));
        $container->register('b')->addArgument(new Reference('c'));
        $container->register('c')->addArgument(new Reference('a'));

        $this->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     */
    public function testProcessDetectsIndirectCircularReferenceWithFactory()
    {
        $container = new ContainerBuilder();

        $container->register('a')->addArgument(new Reference('b'));

        $container
            ->register('b', 'stdClass')
            ->setFactory(array(new Reference('c'), 'getInstance'));

        $container->register('c')->addArgument(new Reference('a'));

        $this->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     */
    public function testDeepCircularReference()
    {
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
        $container->register('b')->addMethodCall('setA', array(new Reference('a')));

        $this->process($container);
    }

    protected function process(ContainerBuilder $container)
    {
        $compiler = new Compiler();
        $passConfig = $compiler->getPassConfig();
        $passConfig->setOptimizationPasses(array(
            new AnalyzeServiceReferencesPass(true),
            new CheckCircularReferencesPass(),
        ));
        $passConfig->setRemovingPasses(array());

        $compiler->compile($container);
    }
}
