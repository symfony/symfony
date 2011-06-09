<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Tests\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Scope;

use Symfony\Component\DependencyInjection\Compiler\CheckReferenceValidityPass;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CheckReferenceValidityPassTest extends \PHPUnit_Framework_TestCase
{
    public function testProcessIgnoresScopeWideningIfNonStrictReference()
    {
        $container = new ContainerBuilder();
        $container->register('a')->addArgument(new Reference('b', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, false));
        $container->register('b')->setScope('prototype');

        $this->process($container);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testProcessDetectsScopeWidening()
    {
        $container = new ContainerBuilder();
        $container->register('a')->addArgument(new Reference('b'));
        $container->register('b')->setScope('prototype');

        $this->process($container);
    }

    public function testProcessIgnoresCrossScopeHierarchyReferenceIfNotStrict()
    {
        $container = new ContainerBuilder();
        $container->addScope(new Scope('a'));
        $container->addScope(new Scope('b'));

        $container->register('a')->setScope('a')->addArgument(new Reference('b', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, false));
        $container->register('b')->setScope('b');

        $this->process($container);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testProcessDetectsCrossScopeHierarchyReference()
    {
        $container = new ContainerBuilder();
        $container->addScope(new Scope('a'));
        $container->addScope(new Scope('b'));

        $container->register('a')->setScope('a')->addArgument(new Reference('b'));
        $container->register('b')->setScope('b');

        $this->process($container);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testProcessDetectsReferenceToAbstractDefinition()
    {
        $container = new ContainerBuilder();

        $container->register('a')->setAbstract(true);
        $container->register('b')->addArgument(new Reference('a'));

        $this->process($container);
    }

    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->register('a')->addArgument(new Reference('b'));
        $container->register('b');

        $this->process($container);
    }

    protected function process(ContainerBuilder $container)
    {
        $pass = new CheckReferenceValidityPass();
        $pass->process($container);
    }
}
