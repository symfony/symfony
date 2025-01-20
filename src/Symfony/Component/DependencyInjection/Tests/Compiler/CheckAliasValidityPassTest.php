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
use Symfony\Component\DependencyInjection\Compiler\CheckAliasValidityPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Tests\Fixtures\CheckAliasValidityPass\FooImplementing;
use Symfony\Component\DependencyInjection\Tests\Fixtures\CheckAliasValidityPass\FooInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\CheckAliasValidityPass\FooNotImplementing;

class CheckAliasValidityPassTest extends TestCase
{
    public function testProcessDetectsClassNotImplementingAliasedInterface()
    {
        $this->expectException(RuntimeException::class);
        $container = new ContainerBuilder();
        $container->register('a')->setClass(FooNotImplementing::class);
        $container->setAlias(FooInterface::class, 'a');

        $this->process($container);
    }

    public function testProcessAcceptsClassImplementingAliasedInterface()
    {
        $container = new ContainerBuilder();
        $container->register('a')->setClass(FooImplementing::class);
        $container->setAlias(FooInterface::class, 'a');

        $this->process($container);
        $this->addToAssertionCount(1);
    }

    public function testProcessIgnoresArbitraryAlias()
    {
        $container = new ContainerBuilder();
        $container->register('a')->setClass(FooImplementing::class);
        $container->setAlias('not_an_interface', 'a');

        $this->process($container);
        $this->addToAssertionCount(1);
    }

    public function testProcessIgnoresTargetWithFactory()
    {
        $container = new ContainerBuilder();
        $container->register('a')->setFactory(new Reference('foo'));
        $container->setAlias(FooInterface::class, 'a');

        $this->process($container);
        $this->addToAssertionCount(1);
    }

    public function testProcessIgnoresTargetWithoutClass()
    {
        $container = new ContainerBuilder();
        $container->register('a');
        $container->setAlias(FooInterface::class, 'a');

        $this->process($container);
        $this->addToAssertionCount(1);
    }

    protected function process(ContainerBuilder $container): void
    {
        $pass = new CheckAliasValidityPass();
        $pass->process($container);
    }
}
