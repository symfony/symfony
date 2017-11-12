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
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\AutowireRequiredMethodsPass;
use Symfony\Component\DependencyInjection\Compiler\AutowirePass;
use Symfony\Component\DependencyInjection\Compiler\ResolveClassPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Tests\Fixtures\includes\FooVariadic;
use Symfony\Component\DependencyInjection\TypedReference;

require_once __DIR__.'/../Fixtures/includes/autowiring_classes.php';

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class AutowirePassTest extends TestCase
{
    public function testProcess(): void
    {
        $container = new ContainerBuilder();

        $container->register(Foo::class);
        $barDefinition = $container->register('bar', __NAMESPACE__.'\Bar');
        $barDefinition->setAutowired(true);

        (new ResolveClassPass())->process($container);
        (new AutowirePass())->process($container);

        $this->assertCount(1, $container->getDefinition('bar')->getArguments());
        $this->assertEquals(Foo::class, (string) $container->getDefinition('bar')->getArgument(0));
    }

    public function testProcessVariadic(): void
    {
        $container = new ContainerBuilder();
        $container->register(Foo::class);
        $definition = $container->register('fooVariadic', FooVariadic::class);
        $definition->setAutowired(true);

        (new ResolveClassPass())->process($container);
        (new AutowirePass())->process($container);

        $this->assertCount(1, $container->getDefinition('fooVariadic')->getArguments());
        $this->assertEquals(Foo::class, (string) $container->getDefinition('fooVariadic')->getArgument(0));
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Cannot autowire service "c": argument "$a" of method "Symfony\Component\DependencyInjection\Tests\Compiler\C::__construct()" references class "Symfony\Component\DependencyInjection\Tests\Compiler\A" but no such service exists. You should maybe alias this class to the existing "Symfony\Component\DependencyInjection\Tests\Compiler\B" service.
     */
    public function testProcessAutowireParent(): void
    {
        $container = new ContainerBuilder();

        $container->register(B::class);
        $cDefinition = $container->register('c', __NAMESPACE__.'\C');
        $cDefinition->setAutowired(true);

        (new ResolveClassPass())->process($container);
        (new AutowirePass())->process($container);

        $this->assertCount(1, $container->getDefinition('c')->getArguments());
        $this->assertEquals(B::class, (string) $container->getDefinition('c')->getArgument(0));
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Cannot autowire service "g": argument "$d" of method "Symfony\Component\DependencyInjection\Tests\Compiler\G::__construct()" references interface "Symfony\Component\DependencyInjection\Tests\Compiler\DInterface" but no such service exists. You should maybe alias this interface to the existing "Symfony\Component\DependencyInjection\Tests\Compiler\F" service.
     */
    public function testProcessAutowireInterface(): void
    {
        $container = new ContainerBuilder();

        $container->register(F::class);
        $gDefinition = $container->register('g', __NAMESPACE__.'\G');
        $gDefinition->setAutowired(true);

        (new ResolveClassPass())->process($container);
        (new AutowirePass())->process($container);

        $this->assertCount(3, $container->getDefinition('g')->getArguments());
        $this->assertEquals(F::class, (string) $container->getDefinition('g')->getArgument(0));
        $this->assertEquals(F::class, (string) $container->getDefinition('g')->getArgument(1));
        $this->assertEquals(F::class, (string) $container->getDefinition('g')->getArgument(2));
    }

    public function testCompleteExistingDefinition(): void
    {
        $container = new ContainerBuilder();

        $container->register('b', __NAMESPACE__.'\B');
        $container->register(DInterface::class, F::class);
        $hDefinition = $container->register('h', __NAMESPACE__.'\H')->addArgument(new Reference('b'));
        $hDefinition->setAutowired(true);

        (new ResolveClassPass())->process($container);
        (new AutowirePass())->process($container);

        $this->assertCount(2, $container->getDefinition('h')->getArguments());
        $this->assertEquals('b', (string) $container->getDefinition('h')->getArgument(0));
        $this->assertEquals(DInterface::class, (string) $container->getDefinition('h')->getArgument(1));
    }

    public function testCompleteExistingDefinitionWithNotDefinedArguments(): void
    {
        $container = new ContainerBuilder();

        $container->register(B::class);
        $container->register(DInterface::class, F::class);
        $hDefinition = $container->register('h', __NAMESPACE__.'\H')->addArgument('')->addArgument('');
        $hDefinition->setAutowired(true);

        (new ResolveClassPass())->process($container);
        (new AutowirePass())->process($container);

        $this->assertCount(2, $container->getDefinition('h')->getArguments());
        $this->assertEquals(B::class, (string) $container->getDefinition('h')->getArgument(0));
        $this->assertEquals(DInterface::class, (string) $container->getDefinition('h')->getArgument(1));
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
     * @expectedExceptionMessage Invalid service "private_service": constructor of class "Symfony\Component\DependencyInjection\Tests\Compiler\PrivateConstructor" must be public.
     */
    public function testPrivateConstructorThrowsAutowireException(): void
    {
        $container = new ContainerBuilder();

        $container->autowire('private_service', __NAMESPACE__.'\PrivateConstructor');

        $pass = new AutowirePass(true);
        $pass->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
     * @expectedExceptionMessage Cannot autowire service "a": argument "$collision" of method "Symfony\Component\DependencyInjection\Tests\Compiler\CannotBeAutowired::__construct()" references interface "Symfony\Component\DependencyInjection\Tests\Compiler\CollisionInterface" but no such service exists. You should maybe alias this interface to one of these existing services: "c1", "c2", "c3".
     */
    public function testTypeCollision(): void
    {
        $container = new ContainerBuilder();

        $container->register('c1', __NAMESPACE__.'\CollisionA');
        $container->register('c2', __NAMESPACE__.'\CollisionB');
        $container->register('c3', __NAMESPACE__.'\CollisionB');
        $aDefinition = $container->register('a', __NAMESPACE__.'\CannotBeAutowired');
        $aDefinition->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
     * @expectedExceptionMessage Cannot autowire service "a": argument "$k" of method "Symfony\Component\DependencyInjection\Tests\Compiler\NotGuessableArgument::__construct()" references class "Symfony\Component\DependencyInjection\Tests\Compiler\Foo" but no such service exists. You should maybe alias this class to one of these existing services: "a1", "a2".
     */
    public function testTypeNotGuessable(): void
    {
        $container = new ContainerBuilder();

        $container->register('a1', __NAMESPACE__.'\Foo');
        $container->register('a2', __NAMESPACE__.'\Foo');
        $aDefinition = $container->register('a', __NAMESPACE__.'\NotGuessableArgument');
        $aDefinition->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
     * @expectedExceptionMessage Cannot autowire service "a": argument "$k" of method "Symfony\Component\DependencyInjection\Tests\Compiler\NotGuessableArgumentForSubclass::__construct()" references class "Symfony\Component\DependencyInjection\Tests\Compiler\A" but no such service exists. You should maybe alias this class to one of these existing services: "a1", "a2".
     */
    public function testTypeNotGuessableWithSubclass(): void
    {
        $container = new ContainerBuilder();

        $container->register('a1', __NAMESPACE__.'\B');
        $container->register('a2', __NAMESPACE__.'\B');
        $aDefinition = $container->register('a', __NAMESPACE__.'\NotGuessableArgumentForSubclass');
        $aDefinition->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
     * @expectedExceptionMessage Cannot autowire service "a": argument "$collision" of method "Symfony\Component\DependencyInjection\Tests\Compiler\CannotBeAutowired::__construct()" references interface "Symfony\Component\DependencyInjection\Tests\Compiler\CollisionInterface" but no such service exists.
     */
    public function testTypeNotGuessableNoServicesFound(): void
    {
        $container = new ContainerBuilder();

        $aDefinition = $container->register('a', __NAMESPACE__.'\CannotBeAutowired');
        $aDefinition->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);
    }

    public function testTypeNotGuessableWithTypeSet(): void
    {
        $container = new ContainerBuilder();

        $container->register('a1', __NAMESPACE__.'\Foo');
        $container->register('a2', __NAMESPACE__.'\Foo');
        $container->register(Foo::class, Foo::class);
        $aDefinition = $container->register('a', __NAMESPACE__.'\NotGuessableArgument');
        $aDefinition->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);

        $this->assertCount(1, $container->getDefinition('a')->getArguments());
        $this->assertEquals(Foo::class, (string) $container->getDefinition('a')->getArgument(0));
    }

    public function testWithTypeSet(): void
    {
        $container = new ContainerBuilder();

        $container->register('c1', __NAMESPACE__.'\CollisionA');
        $container->register('c2', __NAMESPACE__.'\CollisionB');
        $container->setAlias(CollisionInterface::class, 'c2');
        $aDefinition = $container->register('a', __NAMESPACE__.'\CannotBeAutowired');
        $aDefinition->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);

        $this->assertCount(1, $container->getDefinition('a')->getArguments());
        $this->assertEquals(CollisionInterface::class, (string) $container->getDefinition('a')->getArgument(0));
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
     * @expectedExceptionMessage Cannot autowire service "coop_tilleuls": argument "$j" of method "Symfony\Component\DependencyInjection\Tests\Compiler\LesTilleuls::__construct()" references class "Symfony\Component\DependencyInjection\Tests\Compiler\Dunglas" but no such service exists.
     */
    public function testServicesAreNotAutoCreated(): void
    {
        $container = new ContainerBuilder();

        $coopTilleulsDefinition = $container->register('coop_tilleuls', __NAMESPACE__.'\LesTilleuls');
        $coopTilleulsDefinition->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);
    }

    public function testResolveParameter(): void
    {
        $container = new ContainerBuilder();

        $container->setParameter('class_name', Bar::class);
        $container->register(Foo::class);
        $barDefinition = $container->register('bar', '%class_name%');
        $barDefinition->setAutowired(true);

        (new ResolveClassPass())->process($container);
        (new AutowirePass())->process($container);

        $this->assertEquals(Foo::class, $container->getDefinition('bar')->getArgument(0));
    }

    public function testOptionalParameter(): void
    {
        $container = new ContainerBuilder();

        $container->register(A::class);
        $container->register(Foo::class);
        $optDefinition = $container->register('opt', __NAMESPACE__.'\OptionalParameter');
        $optDefinition->setAutowired(true);

        (new ResolveClassPass())->process($container);
        (new AutowirePass())->process($container);

        $definition = $container->getDefinition('opt');
        $this->assertNull($definition->getArgument(0));
        $this->assertEquals(A::class, $definition->getArgument(1));
        $this->assertEquals(Foo::class, $definition->getArgument(2));
    }

    public function testDontTriggerAutowiring(): void
    {
        $container = new ContainerBuilder();

        $container->register(Foo::class);
        $container->register('bar', __NAMESPACE__.'\Bar');

        (new ResolveClassPass())->process($container);
        (new AutowirePass())->process($container);

        $this->assertCount(0, $container->getDefinition('bar')->getArguments());
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
     * @expectedExceptionMessage Cannot autowire service "a": argument "$r" of method "Symfony\Component\DependencyInjection\Tests\Compiler\BadTypeHintedArgument::__construct()" has type "Symfony\Component\DependencyInjection\Tests\Compiler\NotARealClass" but this class cannot be loaded.
     */
    public function testClassNotFoundThrowsException(): void
    {
        $container = new ContainerBuilder();

        $aDefinition = $container->register('a', __NAMESPACE__.'\BadTypeHintedArgument');
        $aDefinition->setAutowired(true);

        $container->register(Dunglas::class, Dunglas::class);

        $pass = new AutowirePass();
        $pass->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
     * @expectedExceptionMessage Cannot autowire service "a": argument "$r" of method "Symfony\Component\DependencyInjection\Tests\Compiler\BadParentTypeHintedArgument::__construct()" has type "Symfony\Component\DependencyInjection\Tests\Compiler\OptionalServiceClass" but this class cannot be loaded.
     */
    public function testParentClassNotFoundThrowsException(): void
    {
        $container = new ContainerBuilder();

        $aDefinition = $container->register('a', __NAMESPACE__.'\BadParentTypeHintedArgument');
        $aDefinition->setAutowired(true);

        $container->register(Dunglas::class, Dunglas::class);

        $pass = new AutowirePass();
        $pass->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
     * @expectedExceptionMessage Cannot autowire service "bar": argument "$foo" of method "Symfony\Component\DependencyInjection\Tests\Compiler\Bar::__construct()" references class "Symfony\Component\DependencyInjection\Tests\Compiler\Foo" but this service is abstract. You should maybe alias this class to the existing "foo" service.
     */
    public function testDontUseAbstractServices(): void
    {
        $container = new ContainerBuilder();

        $container->register(Foo::class)->setAbstract(true);
        $container->register('foo', __NAMESPACE__.'\Foo');
        $container->register('bar', __NAMESPACE__.'\Bar')->setAutowired(true);

        (new ResolveClassPass())->process($container);
        (new AutowirePass())->process($container);
    }

    public function testSomeSpecificArgumentsAreSet(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo', Foo::class);
        $container->register(A::class);
        $container->register(Dunglas::class);
        $container->register('multiple', __NAMESPACE__.'\MultipleArguments')
            ->setAutowired(true)
            // set the 2nd (index 1) argument only: autowire the first and third
            // args are: A, Foo, Dunglas
            ->setArguments(array(
                1 => new Reference('foo'),
            ));

        (new ResolveClassPass())->process($container);
        (new AutowirePass())->process($container);

        $definition = $container->getDefinition('multiple');
        $this->assertEquals(
            array(
                new TypedReference(A::class, A::class, MultipleArguments::class),
                new Reference('foo'),
                new TypedReference(Dunglas::class, Dunglas::class, MultipleArguments::class),
            ),
            $definition->getArguments()
        );
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
     * @expectedExceptionMessage Cannot autowire service "arg_no_type_hint": argument "$foo" of method "Symfony\Component\DependencyInjection\Tests\Compiler\MultipleArguments::__construct()" must have a type-hint or be given a value explicitly.
     */
    public function testScalarArgsCannotBeAutowired(): void
    {
        $container = new ContainerBuilder();

        $container->register(A::class);
        $container->register(Dunglas::class);
        $container->register('arg_no_type_hint', __NAMESPACE__.'\MultipleArguments')
            ->setAutowired(true);

        (new ResolveClassPass())->process($container);
        (new AutowirePass())->process($container);
    }

    public function testOptionalScalarNotReallyOptionalUsesDefaultValue(): void
    {
        $container = new ContainerBuilder();

        $container->register(A::class);
        $container->register(Lille::class);
        $definition = $container->register('not_really_optional_scalar', __NAMESPACE__.'\MultipleArgumentsOptionalScalarNotReallyOptional')
            ->setAutowired(true);

        (new ResolveClassPass())->process($container);
        (new AutowirePass())->process($container);

        $this->assertSame('default_val', $definition->getArgument(1));
    }

    public function testOptionalScalarArgsDontMessUpOrder(): void
    {
        $container = new ContainerBuilder();

        $container->register(A::class);
        $container->register(Lille::class);
        $container->register('with_optional_scalar', __NAMESPACE__.'\MultipleArgumentsOptionalScalar')
            ->setAutowired(true);

        (new ResolveClassPass())->process($container);
        (new AutowirePass())->process($container);

        $definition = $container->getDefinition('with_optional_scalar');
        $this->assertEquals(
            array(
                new TypedReference(A::class, A::class, MultipleArgumentsOptionalScalar::class),
                // use the default value
                'default_val',
                new TypedReference(Lille::class, Lille::class),
            ),
            $definition->getArguments()
        );
    }

    public function testOptionalScalarArgsNotPassedIfLast(): void
    {
        $container = new ContainerBuilder();

        $container->register(A::class);
        $container->register(Lille::class);
        $container->register('with_optional_scalar_last', __NAMESPACE__.'\MultipleArgumentsOptionalScalarLast')
            ->setAutowired(true);

        (new ResolveClassPass())->process($container);
        (new AutowirePass())->process($container);

        $definition = $container->getDefinition('with_optional_scalar_last');
        $this->assertEquals(
            array(
                new TypedReference(A::class, A::class, MultipleArgumentsOptionalScalarLast::class),
                new TypedReference(Lille::class, Lille::class, MultipleArgumentsOptionalScalarLast::class),
            ),
            $definition->getArguments()
        );
    }

    public function testOptionalArgsNoRequiredForCoreClasses(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo', \SplFileObject::class)
            ->addArgument('foo.txt')
            ->setAutowired(true);

        (new AutowirePass())->process($container);

        $definition = $container->getDefinition('foo');
        $this->assertEquals(
            array('foo.txt'),
            $definition->getArguments()
        );
    }

    public function testSetterInjection(): void
    {
        $container = new ContainerBuilder();
        $container->register(Foo::class);
        $container->register(A::class);
        $container->register(CollisionA::class);
        $container->register(CollisionB::class);

        // manually configure *one* call, to override autowiring
        $container
            ->register('setter_injection', SetterInjection::class)
            ->setAutowired(true)
            ->addMethodCall('setWithCallsConfigured', array('manual_arg1', 'manual_arg2'))
        ;

        (new ResolveClassPass())->process($container);
        (new AutowireRequiredMethodsPass())->process($container);
        (new AutowirePass())->process($container);

        $methodCalls = $container->getDefinition('setter_injection')->getMethodCalls();

        $this->assertEquals(
            array('setWithCallsConfigured', 'setFoo', 'setDependencies', 'setChildMethodWithoutDocBlock'),
            array_column($methodCalls, 0)
        );

        // test setWithCallsConfigured args
        $this->assertEquals(
            array('manual_arg1', 'manual_arg2'),
            $methodCalls[0][1]
        );
        // test setFoo args
        $this->assertEquals(
            array(new TypedReference(Foo::class, Foo::class, SetterInjection::class)),
            $methodCalls[1][1]
        );
    }

    public function testExplicitMethodInjection(): void
    {
        $container = new ContainerBuilder();
        $container->register(Foo::class);
        $container->register(A::class);
        $container->register(CollisionA::class);
        $container->register(CollisionB::class);

        $container
            ->register('setter_injection', SetterInjection::class)
            ->setAutowired(true)
            ->addMethodCall('notASetter', array())
        ;

        (new ResolveClassPass())->process($container);
        (new AutowireRequiredMethodsPass())->process($container);
        (new AutowirePass())->process($container);

        $methodCalls = $container->getDefinition('setter_injection')->getMethodCalls();

        $this->assertEquals(
            array('notASetter', 'setFoo', 'setDependencies', 'setWithCallsConfigured', 'setChildMethodWithoutDocBlock'),
            array_column($methodCalls, 0)
        );
        $this->assertEquals(
            array(new TypedReference(A::class, A::class, SetterInjection::class)),
            $methodCalls[0][1]
        );
    }

    public function getCreateResourceTests()
    {
        return array(
            array('IdenticalClassResource', true),
            array('ClassChangedConstructorArgs', false),
        );
    }

    public function testIgnoreServiceWithClassNotExisting(): void
    {
        $container = new ContainerBuilder();

        $container->register('class_not_exist', __NAMESPACE__.'\OptionalServiceClass');

        $barDefinition = $container->register('bar', __NAMESPACE__.'\Bar');
        $barDefinition->setAutowired(true);

        $container->register(Foo::class, Foo::class);

        $pass = new AutowirePass();
        $pass->process($container);

        $this->assertTrue($container->hasDefinition('bar'));
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
     * @expectedExceptionMessage Cannot autowire service "setter_injection_collision": argument "$collision" of method "Symfony\Component\DependencyInjection\Tests\Compiler\SetterInjectionCollision::setMultipleInstancesForOneArg()" references interface "Symfony\Component\DependencyInjection\Tests\Compiler\CollisionInterface" but no such service exists. You should maybe alias this interface to one of these existing services: "c1", "c2".
     */
    public function testSetterInjectionCollisionThrowsException(): void
    {
        $container = new ContainerBuilder();

        $container->register('c1', CollisionA::class);
        $container->register('c2', CollisionB::class);
        $aDefinition = $container->register('setter_injection_collision', SetterInjectionCollision::class);
        $aDefinition->setAutowired(true);

        (new AutowireRequiredMethodsPass())->process($container);

        $pass = new AutowirePass();
        $pass->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
     * @expectedExceptionMessage Cannot autowire service "bar": argument "$foo" of method "Symfony\Component\DependencyInjection\Tests\Compiler\Bar::__construct()" references class "Symfony\Component\DependencyInjection\Tests\Compiler\Foo" but no such service exists. You should maybe alias this class to the existing "foo" service.
     */
    public function testProcessDoesNotTriggerDeprecations(): void
    {
        $container = new ContainerBuilder();
        $container->register('deprecated', 'Symfony\Component\DependencyInjection\Tests\Fixtures\DeprecatedClass')->setDeprecated(true);
        $container->register('foo', __NAMESPACE__.'\Foo');
        $container->register('bar', __NAMESPACE__.'\Bar')->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);

        $this->assertTrue($container->hasDefinition('deprecated'));
        $this->assertTrue($container->hasDefinition('foo'));
        $this->assertTrue($container->hasDefinition('bar'));
    }

    public function testEmptyStringIsKept(): void
    {
        $container = new ContainerBuilder();

        $container->register(A::class);
        $container->register(Lille::class);
        $container->register('foo', __NAMESPACE__.'\MultipleArgumentsOptionalScalar')
            ->setAutowired(true)
            ->setArguments(array('', ''));

        (new ResolveClassPass())->process($container);
        (new AutowirePass())->process($container);

        $this->assertEquals(array(new TypedReference(A::class, A::class, MultipleArgumentsOptionalScalar::class), '', new TypedReference(Lille::class, Lille::class)), $container->getDefinition('foo')->getArguments());
    }

    public function testWithFactory(): void
    {
        $container = new ContainerBuilder();

        $container->register(Foo::class);
        $definition = $container->register('a', A::class)
            ->setFactory(array(A::class, 'create'))
            ->setAutowired(true);

        (new ResolveClassPass())->process($container);
        (new AutowirePass())->process($container);

        $this->assertEquals(array(new TypedReference(Foo::class, Foo::class, A::class)), $definition->getArguments());
    }

    /**
     * @dataProvider provideNotWireableCalls
     * @expectedException \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
     */
    public function testNotWireableCalls($method, $expectedMsg): void
    {
        $container = new ContainerBuilder();

        $foo = $container->register('foo', NotWireable::class)->setAutowired(true)
            ->addMethodCall('setBar', array())
            ->addMethodCall('setOptionalNotAutowireable', array())
            ->addMethodCall('setOptionalNoTypeHint', array())
            ->addMethodCall('setOptionalArgNoAutowireable', array())
        ;

        if ($method) {
            $foo->addMethodCall($method, array());
        }

        if (method_exists($this, 'expectException')) {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage($expectedMsg);
        } else {
            $this->setExpectedException(RuntimeException::class, $expectedMsg);
        }

        (new ResolveClassPass())->process($container);
        (new AutowireRequiredMethodsPass())->process($container);
        (new AutowirePass())->process($container);
    }

    public function provideNotWireableCalls()
    {
        return array(
            array('setNotAutowireable', 'Cannot autowire service "foo": argument "$n" of method "Symfony\Component\DependencyInjection\Tests\Compiler\NotWireable::setNotAutowireable()" has type "Symfony\Component\DependencyInjection\Tests\Compiler\NotARealClass" but this class cannot be loaded.'),
            array('setDifferentNamespace', 'Cannot autowire service "foo": argument "$n" of method "Symfony\Component\DependencyInjection\Tests\Compiler\NotWireable::setDifferentNamespace()" references class "stdClass" but no such service exists. It cannot be auto-registered because it is from a different root namespace.'),
            array(null, 'Invalid service "foo": method "Symfony\Component\DependencyInjection\Tests\Compiler\NotWireable::setProtectedMethod()" must be public.'),
        );
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
     * @expectedExceptionMessage Cannot autowire service "j": argument "$i" of method "Symfony\Component\DependencyInjection\Tests\Compiler\J::__construct()" references class "Symfony\Component\DependencyInjection\Tests\Compiler\I" but no such service exists. Try changing the type-hint to "Symfony\Component\DependencyInjection\Tests\Compiler\IInterface" instead.
     */
    public function testByIdAlternative(): void
    {
        $container = new ContainerBuilder();

        $container->setAlias(IInterface::class, 'i');
        $container->register('i', I::class);
        $container->register('j', J::class)
            ->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
     * @expectedExceptionMessage Cannot autowire service "j": argument "$i" of method "Symfony\Component\DependencyInjection\Tests\Compiler\J::__construct()" references class "Symfony\Component\DependencyInjection\Tests\Compiler\I" but no such service exists. Try changing the type-hint to "Symfony\Component\DependencyInjection\Tests\Compiler\IInterface" instead.
     */
    public function testExceptionWhenAliasExists(): void
    {
        $container = new ContainerBuilder();

        // multiple I services... but there *is* IInterface available
        $container->setAlias(IInterface::class, 'i');
        $container->register('i', I::class);
        $container->register('i2', I::class);
        // J type-hints against I concretely
        $container->register('j', J::class)
            ->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
     * @expectedExceptionMessage Cannot autowire service "j": argument "$i" of method "Symfony\Component\DependencyInjection\Tests\Compiler\J::__construct()" references class "Symfony\Component\DependencyInjection\Tests\Compiler\I" but no such service exists. You should maybe alias this class to one of these existing services: "i", "i2".
     */
    public function testExceptionWhenAliasDoesNotExist(): void
    {
        $container = new ContainerBuilder();

        // multiple I instances... but no IInterface alias
        $container->register('i', I::class);
        $container->register('i2', I::class);
        // J type-hints against I concretely
        $container->register('j', J::class)
            ->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);
    }

    public function testInlineServicesAreNotCandidates(): void
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(realpath(__DIR__.'/../Fixtures/xml')));
        $loader->load('services_inline_not_candidate.xml');

        $pass = new AutowirePass();
        $pass->process($container);

        $this->assertSame(array(), $container->getDefinition('autowired')->getArguments());
    }
}
