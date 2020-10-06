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
use Symfony\Component\DependencyInjection\Compiler\AutoDecorationServicePass;
use Symfony\Component\DependencyInjection\Compiler\DecoratorServicePass;
use Symfony\Component\DependencyInjection\Compiler\ResolveClassPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Dummy;
use Symfony\Component\DependencyInjection\Tests\Fixtures\DummyDecorator1;
use Symfony\Component\DependencyInjection\Tests\Fixtures\DummyDecorator2;
use Symfony\Component\DependencyInjection\Tests\Fixtures\DummyDecorator3;

class AutoDecorationServicePassTest extends TestCase
{
    public function testProcessUsingFQCN()
    {
        $container = new ContainerBuilder();
        $dummyDefinition = $container
            ->register(Dummy::class)
            ->setPublic(true)
        ;
        $decoratorDefinition = $container
            ->register('dummy.extended', DummyDecorator1::class)
            ->setPublic(true)
        ;

        $this->process($container);

        $this->assertSame($dummyDefinition, $container->getDefinition('dummy.extended.inner'));
        $this->assertFalse($container->getDefinition('dummy.extended.inner')->isPublic());

        $this->assertNull($decoratorDefinition->getDecoratedService());
    }

    public function testProcessUsingStringServiceId()
    {
        $container = new ContainerBuilder();
        $dummyDefinition = $container
            ->register('dummy', Dummy::class)
            ->setPublic(true)
        ;
        $decoratorDefinition = $container
            ->register('dummy.extended', DummyDecorator2::class)
            ->setPublic(true)
        ;

        $this->process($container);

        $this->assertSame($dummyDefinition, $container->getDefinition('dummy.extended.inner'));
        $this->assertFalse($container->getDefinition('dummy.extended.inner')->isPublic());

        $this->assertNull($decoratorDefinition->getDecoratedService());
    }

    public function testProcessWithDecorationPriority()
    {
        $container = new ContainerBuilder();
        $dummyDefinition = $container
            ->register(Dummy::class)
            ->setPublic(true)
        ;
        $decoratorWithHighPriorityDefinition = $container
            ->register('dummy.extended', DummyDecorator3::class)
            ->setPublic(true)
        ;
        $decoratorDefinition = $container
            ->register('dummy.extended.extended', DummyDecorator1::class)
            ->setPublic(true)
        ;

        $this->process($container);

        $this->assertSame($dummyDefinition, $container->getDefinition('dummy.extended.inner'));
        $this->assertFalse($container->getDefinition('dummy.extended.inner')->isPublic());

        $this->assertEquals('dummy.extended', $container->getAlias('dummy.extended.extended.inner'));
        $this->assertFalse($container->getAlias('dummy.extended.extended.inner')->isPublic());

        $this->assertNull($decoratorWithHighPriorityDefinition->getDecoratedService());
        $this->assertNull($decoratorDefinition->getDecoratedService());
    }

    protected function process(ContainerBuilder $container)
    {
        (new ResolveClassPass())->process($container);
        (new AutoDecorationServicePass())->process($container);
        (new DecoratorServicePass())->process($container);
    }
}
