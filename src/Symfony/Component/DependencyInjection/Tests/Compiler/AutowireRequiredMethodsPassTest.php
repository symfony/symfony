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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\DependencyInjection\Compiler\AutowireRequiredMethodsPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveClassPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Tests\Fixtures\WitherAnnotationStaticReturnType;
use Symfony\Component\DependencyInjection\Tests\Fixtures\WitherStaticReturnType;

require_once __DIR__.'/../Fixtures/includes/autowiring_classes.php';

class AutowireRequiredMethodsPassTest extends TestCase
{
    use ExpectDeprecationTrait;

    /**
     * @group legacy
     */
    public function testSetterInjectionAnnotation()
    {
        $this->expectDeprecation('Since symfony/dependency-injection 6.3: Relying on the "@required" annotation on method "Symfony\Component\DependencyInjection\Tests\Compiler\SetterInjectionAnnotation::setFoo()" is deprecated, use the "Symfony\Contracts\Service\Attribute\Required" attribute instead.');
        $this->expectDeprecation('Since symfony/dependency-injection 6.3: Relying on the "@required" annotation on method "Symfony\Component\DependencyInjection\Tests\Compiler\SetterInjectionAnnotation::setChildMethodWithoutDocBlock()" is deprecated, use the "Symfony\Contracts\Service\Attribute\Required" attribute instead.');
        $this->expectDeprecation('Since symfony/dependency-injection 6.3: Relying on the "@required" annotation on method "Symfony\Component\DependencyInjection\Tests\Compiler\SetterInjectionParentAnnotation::setDependencies()" is deprecated, use the "Symfony\Contracts\Service\Attribute\Required" attribute instead.');

        $container = new ContainerBuilder();
        $container->register(FooAnnotation::class);
        $container->register(A::class);
        $container->register(CollisionA::class);
        $container->register(CollisionB::class);

        // manually configure *one* call, to override autowiring
        $container
            ->register('setter_injection', SetterInjectionAnnotation::class)
            ->setAutowired(true)
            ->addMethodCall('setWithCallsConfigured', ['manual_arg1', 'manual_arg2']);

        (new ResolveClassPass())->process($container);
        (new AutowireRequiredMethodsPass())->process($container);

        $methodCalls = $container->getDefinition('setter_injection')->getMethodCalls();

        $this->assertEquals(
            ['setWithCallsConfigured', 'setFoo', 'setChildMethodWithoutDocBlock', 'setDependencies'],
            array_column($methodCalls, 0)
        );

        // test setWithCallsConfigured args
        $this->assertEquals(
            ['manual_arg1', 'manual_arg2'],
            $methodCalls[0][1]
        );
        // test setFoo args
        $this->assertEquals([], $methodCalls[1][1]);
    }

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

    /**
     * @group legacy
     */
    // @deprecated since Symfony 6.3, to be removed in 7.0
    public function testExplicitMethodInjectionAnnotation()
    {
        $this->expectDeprecation('Since symfony/dependency-injection 6.3: Relying on the "@required" annotation on method "Symfony\Component\DependencyInjection\Tests\Compiler\SetterInjectionAnnotation::setFoo()" is deprecated, use the "Symfony\Contracts\Service\Attribute\Required" attribute instead.');
        $this->expectDeprecation('Since symfony/dependency-injection 6.3: Relying on the "@required" annotation on method "Symfony\Component\DependencyInjection\Tests\Compiler\SetterInjectionAnnotation::setChildMethodWithoutDocBlock()" is deprecated, use the "Symfony\Contracts\Service\Attribute\Required" attribute instead.');
        $this->expectDeprecation('Since symfony/dependency-injection 6.3: Relying on the "@required" annotation on method "Symfony\Component\DependencyInjection\Tests\Compiler\SetterInjectionParentAnnotation::setDependencies()" is deprecated, use the "Symfony\Contracts\Service\Attribute\Required" attribute instead.');
        $this->expectDeprecation('Since symfony/dependency-injection 6.3: Relying on the "@required" annotation on method "Symfony\Component\DependencyInjection\Tests\Compiler\SetterInjectionParentAnnotation::setWithCallsConfigured()" is deprecated, use the "Symfony\Contracts\Service\Attribute\Required" attribute instead.');

        $container = new ContainerBuilder();
        $container->register(FooAnnotation::class);
        $container->register(A::class);
        $container->register(CollisionA::class);
        $container->register(CollisionB::class);

        $container
            ->register('setter_injection', SetterInjectionAnnotation::class)
            ->setAutowired(true)
            ->addMethodCall('notASetter', []);

        (new ResolveClassPass())->process($container);
        (new AutowireRequiredMethodsPass())->process($container);

        $methodCalls = $container->getDefinition('setter_injection')->getMethodCalls();

        $this->assertEquals(
            ['notASetter', 'setFoo', 'setChildMethodWithoutDocBlock', 'setDependencies', 'setWithCallsConfigured'],
            array_column($methodCalls, 0)
        );
        $this->assertEquals([], $methodCalls[0][1]);
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

    /**
     * @group legacy
     */
    public function testWitherInjection()
    {
        $this->expectDeprecation('Since symfony/dependency-injection 6.3: Relying on the "@required" annotation on method "Symfony\Component\DependencyInjection\Tests\Compiler\WitherAnnotation::withFoo1()" is deprecated, use the "Symfony\Contracts\Service\Attribute\Required" attribute instead.');
        $this->expectDeprecation('Since symfony/dependency-injection 6.3: Relying on the "@required" annotation on method "Symfony\Component\DependencyInjection\Tests\Compiler\WitherAnnotation::withFoo2()" is deprecated, use the "Symfony\Contracts\Service\Attribute\Required" attribute instead.');

        $container = new ContainerBuilder();
        $container->register(FooAnnotation::class);

        $container
            ->register('wither', WitherAnnotation::class)
            ->setAutowired(true);

        (new ResolveClassPass())->process($container);
        (new AutowireRequiredMethodsPass())->process($container);

        $methodCalls = $container->getDefinition('wither')->getMethodCalls();

        $expected = [
            ['withFoo1', [], true],
            ['withFoo2', [], true],
            ['setFoo', []],
        ];
        $this->assertSame($expected, $methodCalls);
    }

    /**
     * @group legacy
     */
    public function testWitherAnnotationWithStaticReturnTypeInjection()
    {
        $this->expectDeprecation('Since symfony/dependency-injection 6.3: Relying on the "@required" annotation on method "Symfony\Component\DependencyInjection\Tests\Fixtures\WitherAnnotationStaticReturnType::withFoo()" is deprecated, use the "Symfony\Contracts\Service\Attribute\Required" attribute instead.');
        $this->expectDeprecation('Since symfony/dependency-injection 6.3: Relying on the "@required" annotation on method "Symfony\Component\DependencyInjection\Tests\Fixtures\WitherAnnotationStaticReturnType::setFoo()" is deprecated, use the "Symfony\Contracts\Service\Attribute\Required" attribute instead.');

        $container = new ContainerBuilder();
        $container->register(FooAnnotation::class);

        $container
            ->register('wither', WitherAnnotationStaticReturnType::class)
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
