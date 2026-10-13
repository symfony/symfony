<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\DependencyInjection\CompilerPass;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\ExposeDecoratedManagersPass;
use Symfony\Bridge\Doctrine\Tests\Fixtures\DummyManager;
use Symfony\Bridge\Doctrine\Tests\Fixtures\DummyManagerDecorator;
use Symfony\Bridge\Doctrine\Tests\TestManagerRegistry;
use Symfony\Component\DependencyInjection\Compiler\DecoratorServicePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ExposeDecoratedManagersPassTest extends TestCase
{
    public function testDecoratedManagerIsMadePublicAndMapped()
    {
        $container = $this->createContainer();
        $container->register('foo.decorator', DummyManagerDecorator::class)
            ->setDecoratedService('foo')
            ->setArguments([new Reference('.inner')]);

        $this->process($container);

        $this->assertTrue($container->getDefinition('foo.decorator.inner')->isPublic());
        $this->assertSame(
            [['setDecoratedManagers', [['foo' => 'foo.decorator.inner']]]],
            $container->getDefinition('doctrine')->getMethodCalls()
        );
    }

    public function testInnerNameIsReadFromTheDecorationRatherThanGuessed()
    {
        $container = $this->createContainer();
        $container->register('foo.decorator', DummyManagerDecorator::class)
            ->setDecoratedService('foo', 'a.name.of.our.own')
            ->setArguments([new Reference('.inner')]);

        $this->process($container);

        $this->assertTrue($container->getDefinition('a.name.of.our.own')->isPublic());
        $this->assertSame(
            [['setDecoratedManagers', [['foo' => 'a.name.of.our.own']]]],
            $container->getDefinition('doctrine')->getMethodCalls()
        );
    }

    public function testInnermostManagerIsMappedWhenDecoratedTwice()
    {
        $container = $this->createContainer();
        $container->register('foo.inner_decorator', DummyManagerDecorator::class)
            ->setDecoratedService('foo', null, 10)
            ->setArguments([new Reference('.inner')]);
        $container->register('foo.outer_decorator', DummyManagerDecorator::class)
            ->setDecoratedService('foo', null, 0)
            ->setArguments([new Reference('.inner')]);

        $this->process($container);

        [[, [$decoratedManagers]]] = $container->getDefinition('doctrine')->getMethodCalls();

        $this->assertSame(DummyManager::class, $container->getDefinition($decoratedManagers['foo'])->getClass());
    }

    public function testDecoratedNonManagerIsIgnored()
    {
        $container = $this->createContainer();
        $container->register('bar', \stdClass::class)->setPublic(true);
        $container->register('bar.decorator', \stdClass::class)
            ->setDecoratedService('bar')
            ->setArguments([new Reference('.inner')]);

        $this->process($container);

        $this->assertFalse($container->getDefinition('bar.decorator.inner')->isPublic());
        $this->assertSame([], $container->getDefinition('doctrine')->getMethodCalls());
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('doctrine', TestManagerRegistry::class)->setPublic(true);
        $container->register('foo', DummyManager::class)->setPublic(true);

        return $container;
    }

    private function process(ContainerBuilder $container): void
    {
        (new DecoratorServicePass())->process($container);
        (new ExposeDecoratedManagersPass())->process($container);
    }
}
