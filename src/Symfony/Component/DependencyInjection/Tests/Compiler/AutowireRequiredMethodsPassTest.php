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
use Symfony\Component\DependencyInjection\Compiler\AutowireRequiredMethodsPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveClassPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Tests\Fixtures\WitherStaticReturnType;

require_once __DIR__.'/../Fixtures/includes/autowiring_classes.php';

class AutowireRequiredMethodsPassTest extends TestCase
{
    public function testSetterInjectionWithAttribute()
    {
        $container = new ContainerBuilder();
        $container->register(Foo::class);

        $container
            ->register('setter_injection', AutowireSetter::class)
            ->setAutowired(true);

        (new ResolveClassPass())->process($container);
        (new AutowireRequiredMethodsPass())->process($container);

        $methodCalls = $container->getDefinition('setter_injection')->getMethodCalls();
        $this->assertSame([['setFoo', []]], $methodCalls);
    }

    public function testExplicitMethodInjectionAttribute()
    {
        $container = new ContainerBuilder();
        $container->register(Foo::class);
        $container->register(A::class);
        $container->register(CollisionA::class);
        $container->register(CollisionB::class);

        $container
            ->register('setter_injection', SetterInjection::class)
            ->setAutowired(true)
            ->addMethodCall('notASetter', []);

        (new ResolveClassPass())->process($container);
        (new AutowireRequiredMethodsPass())->process($container);

        $methodCalls = $container->getDefinition('setter_injection')->getMethodCalls();

        $this->assertEquals(
            ['notASetter', 'setFoo', 'setDependencies', 'setWithCallsConfigured', 'setChildMethodWithoutDocBlock'],
            array_column($methodCalls, 0)
        );
        $this->assertEquals([], $methodCalls[0][1]);
    }

    public function testWitherWithStaticReturnTypeInjection()
    {
        $container = new ContainerBuilder();
        $container->register(Foo::class);

        $container
            ->register('wither', WitherStaticReturnType::class)
            ->setAutowired(true);

        (new ResolveClassPass())->process($container);
        (new AutowireRequiredMethodsPass())->process($container);

        $methodCalls = $container->getDefinition('wither')->getMethodCalls();

        $expected = [
            ['withFoo', [], true],
            ['setFoo', []],
        ];
        $this->assertSame($expected, $methodCalls);
    }

    public function testWitherInjectionWithAttribute()
    {
        $container = new ContainerBuilder();
        $container->register(Foo::class);

        $container
            ->register('wither', AutowireWither::class)
            ->setAutowired(true);

        (new ResolveClassPass())->process($container);
        (new AutowireRequiredMethodsPass())->process($container);

        $this->assertSame([['withFoo', [], true]], $container->getDefinition('wither')->getMethodCalls());
    }
}
