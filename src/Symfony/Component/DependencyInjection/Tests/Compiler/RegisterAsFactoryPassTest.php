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
use Symfony\Component\DependencyInjection\Attribute\AsFactory;
use Symfony\Component\DependencyInjection\Compiler\RegisterAsFactoryPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

class RegisterAsFactoryPassTest extends TestCase
{
    public function testInvokableFactoryClass()
    {
        $container = new ContainerBuilder();
        $container->register('factory', InvokableFactory::class)
            ->setAutoconfigured(true);

        new RegisterAsFactoryPass()->process($container);

        $this->assertTrue($container->hasDefinition(FactoryCreatedService::class));
        $definition = $container->getDefinition(FactoryCreatedService::class);
        $this->assertSame(FactoryCreatedService::class, $definition->getClass());
        $this->assertEquals([new Reference('factory'), '__invoke'], $definition->getFactory());
    }

    public function testNonStaticFactoryMethod()
    {
        $container = new ContainerBuilder();
        $container->register('factory', MethodFactory::class)
            ->setAutoconfigured(true);

        new RegisterAsFactoryPass()->process($container);

        $this->assertTrue($container->hasDefinition(FactoryCreatedService::class));
        $definition = $container->getDefinition(FactoryCreatedService::class);
        $this->assertSame(FactoryCreatedService::class, $definition->getClass());
        $this->assertEquals([new Reference('factory'), 'create'], $definition->getFactory());
    }

    public function testStaticFactoryMethod()
    {
        $container = new ContainerBuilder();
        $container->register(StaticMethodFactory::class, StaticMethodFactory::class)
            ->setAutoconfigured(true);

        new RegisterAsFactoryPass()->process($container);

        $this->assertTrue($container->hasDefinition(FactoryCreatedService::class));
        $definition = $container->getDefinition(FactoryCreatedService::class);
        $this->assertSame(FactoryCreatedService::class, $definition->getClass());
        $this->assertSame([StaticMethodFactory::class, 'create'], $definition->getFactory());
    }

    public function testExplicitIdWithReturnTypeDerivesClass()
    {
        $container = new ContainerBuilder();
        $container->register(ExplicitIdFactory::class, ExplicitIdFactory::class)
            ->setAutoconfigured(true);

        new RegisterAsFactoryPass()->process($container);

        $this->assertTrue($container->hasDefinition('explicit.service.id'));
        $definition = $container->getDefinition('explicit.service.id');
        $this->assertSame(FactoryCreatedService::class, $definition->getClass());
        $this->assertEquals([new Reference(ExplicitIdFactory::class), '__invoke'], $definition->getFactory());
    }

    public function testMergesFactoryIntoExistingDefinition()
    {
        $container = new ContainerBuilder();
        $container->register(InvokableFactory::class, InvokableFactory::class)
            ->setAutoconfigured(true);
        $container->register(FactoryCreatedService::class, FactoryCreatedService::class)
            ->addTag('my.tag')
            ->setArguments(['pre-existing']);

        new RegisterAsFactoryPass()->process($container);

        $definition = $container->getDefinition(FactoryCreatedService::class);
        $this->assertEquals([new Reference(InvokableFactory::class), '__invoke'], $definition->getFactory());
        $this->assertTrue($definition->hasTag('my.tag'));
        $this->assertSame(['pre-existing'], $definition->getArguments());
    }

    public function testSkipsNonAutoconfiguredServices()
    {
        $container = new ContainerBuilder();
        $container->register(InvokableFactory::class, InvokableFactory::class)
            ->setAutoconfigured(false);

        new RegisterAsFactoryPass()->process($container);

        $this->assertFalse($container->hasDefinition(FactoryCreatedService::class));
    }

    public function testThrowsWhenClassNotInvokable()
    {
        $container = new ContainerBuilder();
        $container->register(NonInvokableFactory::class, NonInvokableFactory::class)
            ->setAutoconfigured(true);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('requires the class to be invokable');
        new RegisterAsFactoryPass()->process($container);
    }

    public function testThrowsWhenNoReturnType()
    {
        $container = new ContainerBuilder();
        $container->register(NoReturnTypeFactory::class, NoReturnTypeFactory::class)
            ->setAutoconfigured(true);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('requires either an explicit "id" or a return type declaration');
        new RegisterAsFactoryPass()->process($container);
    }

    public function testThrowsWhenBuiltinReturnType()
    {
        $container = new ContainerBuilder();
        $container->register(BuiltinReturnTypeFactory::class, BuiltinReturnTypeFactory::class)
            ->setAutoconfigured(true);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('requires a class or interface return type');
        new RegisterAsFactoryPass()->process($container);
    }

    public function testThrowsOnUnionReturnType()
    {
        $container = new ContainerBuilder();
        $container->register(UnionReturnTypeFactory::class, UnionReturnTypeFactory::class)
            ->setAutoconfigured(true);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('does not support union or intersection return types');
        new RegisterAsFactoryPass()->process($container);
    }

    public function testFactoryRegisteredUnderACustomServiceId()
    {
        $container = new ContainerBuilder();
        $container->register('app.my_factory', InvokableFactory::class)
            ->setAutoconfigured(true);

        new RegisterAsFactoryPass()->process($container);

        $definition = $container->getDefinition(FactoryCreatedService::class);
        $this->assertEquals([new Reference('app.my_factory'), '__invoke'], $definition->getFactory());
    }

    public function testFactoryMethodInheritedFromParent()
    {
        $container = new ContainerBuilder();
        $container->register('app.child_factory', ChildOfMethodFactory::class)
            ->setAutoconfigured(true);

        new RegisterAsFactoryPass()->process($container);

        $definition = $container->getDefinition(FactoryCreatedService::class);
        $this->assertEquals([new Reference('app.child_factory'), 'create'], $definition->getFactory());
    }

    public function testCreatedServiceInheritsAutowiringSoFactoryArgumentsGetResolved()
    {
        $container = new ContainerBuilder();
        $container->register('app.arg_factory', ArgumentsFactory::class)
            ->setAutoconfigured(true)
            ->setAutowired(true);
        $container->register(FactoryDependency::class, FactoryDependency::class);
        $container->setAlias('public_alias', FactoryCreatedService::class)->setPublic(true);

        $container->compile();

        $this->assertInstanceOf(FactoryCreatedService::class, $container->get('public_alias'));
    }

    public function testNamedConstructorReturningStatic()
    {
        $container = new ContainerBuilder();
        $container->register(StaticReturnTypeFactory::class, StaticReturnTypeFactory::class)
            ->setAutoconfigured(true)
            ->setPublic(true);
        $container->compile();

        $definition = $container->getDefinition(StaticReturnTypeFactory::class);
        $this->assertSame([StaticReturnTypeFactory::class, 'create'], $definition->getFactory());
    }

    public function testNamedConstructorWithParentReturnType()
    {
        $container = new ContainerBuilder();
        $container->register(ParentFactory::class, ParentFactory::class)
            ->setAutoconfigured(true)
            ->setPublic(true);
        $container->register(ChildFactory::class, ChildFactory::class)
            ->setAutoconfigured(true)
            ->setPublic(true);
        $container->compile();

        $definition = $container->getDefinition(ParentFactory::class);
        $this->assertSame([ChildFactory::class, 'create'], $definition->getFactory());
    }

    public function testSkipsExcludedFactories()
    {
        $container = new ContainerBuilder();
        $container->register(InvokableFactory::class, InvokableFactory::class)
            ->setAutoconfigured(true)
            ->setAbstract(true)
            ->addTag('container.excluded');

        $container->compile();

        $this->assertFalse($container->hasDefinition(FactoryCreatedService::class));
    }

    public function testThrowsWhenTwoFactoriesDeclareTheSameServiceId()
    {
        $container = new ContainerBuilder();
        $container->register(ExplicitIdFactory::class, ExplicitIdFactory::class)
            ->setAutoconfigured(true);
        $container->register(ConflictingExplicitIdFactory::class, ConflictingExplicitIdFactory::class)
            ->setAutoconfigured(true);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('which already has a factory');
        new RegisterAsFactoryPass()->process($container);
    }

    public function testThrowsWhenTheServiceIdIsAnAlias()
    {
        $container = new ContainerBuilder();
        $container->register(ExplicitIdFactory::class, ExplicitIdFactory::class)
            ->setAutoconfigured(true);
        $container->register('some.service', FactoryCreatedService::class);
        $container->setAlias('explicit.service.id', 'some.service');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('which is already defined as an alias');
        new RegisterAsFactoryPass()->process($container);
    }

    public function testThrowsWhenTheServiceAlreadyHasAFactory()
    {
        $container = new ContainerBuilder();
        $container->register(InvokableFactory::class, InvokableFactory::class)
            ->setAutoconfigured(true);
        $container->register(FactoryCreatedService::class, FactoryCreatedService::class)
            ->setFactory([FactoryCreatedService::class, 'create']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('which already has a factory');
        new RegisterAsFactoryPass()->process($container);
    }
}

class FactoryCreatedService
{
}

class AnotherFactoryCreatedService
{
}

#[AsFactory]
class InvokableFactory
{
    public function __invoke(): FactoryCreatedService
    {
        return new FactoryCreatedService();
    }
}

class MethodFactory
{
    #[AsFactory]
    public function create(): FactoryCreatedService
    {
        return new FactoryCreatedService();
    }
}

#[AsFactory(id: 'explicit.service.id')]
class ExplicitIdFactory
{
    public function __invoke(): FactoryCreatedService
    {
        return new FactoryCreatedService();
    }
}

#[AsFactory]
class NonInvokableFactory
{
    // No __invoke method
}

#[AsFactory]
class NoReturnTypeFactory
{
    public function __invoke()
    {
    }
}

#[AsFactory]
class BuiltinReturnTypeFactory
{
    public function __invoke(): string
    {
        return '';
    }
}

class StaticMethodFactory
{
    #[AsFactory]
    public static function create(): FactoryCreatedService
    {
        return new FactoryCreatedService();
    }
}

#[AsFactory]
class UnionReturnTypeFactory
{
    public function __invoke(): FactoryCreatedService|AnotherFactoryCreatedService
    {
        return new FactoryCreatedService();
    }
}

class ChildOfMethodFactory extends MethodFactory
{
}

class FactoryDependency
{
}

class ArgumentsFactory
{
    #[AsFactory]
    public function create(FactoryDependency $dependency): FactoryCreatedService
    {
        return new FactoryCreatedService();
    }
}

class StaticReturnTypeFactory
{
    #[AsFactory]
    public static function create(): static
    {
        return new static();
    }
}

#[AsFactory(id: 'explicit.service.id')]
class ConflictingExplicitIdFactory
{
    public function __invoke(): AnotherFactoryCreatedService
    {
        return new AnotherFactoryCreatedService();
    }
}

class ParentFactory
{
    public static function create(): self
    {
        return new self();
    }
}

class ChildFactory extends ParentFactory
{
    #[AsFactory]
    public static function create(): parent
    {
        return parent::create();
    }
}
