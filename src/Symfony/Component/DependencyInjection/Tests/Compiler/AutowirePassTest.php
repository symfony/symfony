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
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\AutowirePass;
use Symfony\Component\DependencyInjection\Compiler\AutowireRequiredMethodsPass;
use Symfony\Component\DependencyInjection\Compiler\DecoratorServicePass;
use Symfony\Component\DependencyInjection\Compiler\ResolveClassPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\AutowiringFailedException;
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
    public function testProcess()
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

    public function testProcessVariadic()
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
    public function testProcessAutowireParent()
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
    public function testProcessAutowireInterface()
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

    public function testCompleteExistingDefinition()
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

    public function testCompleteExistingDefinitionWithNotDefinedArguments()
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
    public function testPrivateConstructorThrowsAutowireException()
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
    public function testTypeCollision()
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
    public function testTypeNotGuessable()
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
    public function testTypeNotGuessableWithSubclass()
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
    public function testTypeNotGuessableNoServicesFound()
    {
        $container = new ContainerBuilder();

        $aDefinition = $container->register('a', __NAMESPACE__.'\CannotBeAutowired');
        $aDefinition->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);
    }

    public function testTypeNotGuessableWithTypeSet()
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

    public function testWithTypeSet()
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
    public function testServicesAreNotAutoCreated()
    {
        $container = new ContainerBuilder();

        $coopTilleulsDefinition = $container->register('coop_tilleuls', __NAMESPACE__.'\LesTilleuls');
        $coopTilleulsDefinition->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);
    }

    public function testResolveParameter()
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

    public function testOptionalParameter()
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

    public function testDontTriggerAutowiring()
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
     * @expectedExceptionMessage Cannot autowire service "a": argument "$r" of method "Symfony\Component\DependencyInjection\Tests\Compiler\BadTypeHintedArgument::__construct()" has type "Symfony\Component\DependencyInjection\Tests\Compiler\NotARealClass" but this class was not found.
     */
    public function testClassNotFoundThrowsException()
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
     * @expectedExceptionMessage Cannot autowire service "a": argument "$r" of method "Symfony\Component\DependencyInjection\Tests\Compiler\BadParentTypeHintedArgument::__construct()" has type "Symfony\Component\DependencyInjection\Tests\Compiler\OptionalServiceClass" but this class is missing a parent class (Class Symfony\Bug\NotExistClass not found).
     */
    public function testParentClassNotFoundThrowsException()
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
    public function testDontUseAbstractServices()
    {
        $container = new ContainerBuilder();

        $container->register(Foo::class)->setAbstract(true);
        $container->register('foo', __NAMESPACE__.'\Foo');
        $container->register('bar', __NAMESPACE__.'\Bar')->setAutowired(true);

        (new ResolveClassPass())->process($container);
        (new AutowirePass())->process($container);
    }

    public function testSomeSpecificArgumentsAreSet()
    {
        $container = new ContainerBuilder();

        $container->register('foo', Foo::class);
        $container->register(A::class);
        $container->register(Dunglas::class);
        $container->register('multiple', __NAMESPACE__.'\MultipleArguments')
            ->setAutowired(true)
            // set the 2nd (index 1) argument only: autowire the first and third
            // args are: A, Foo, Dunglas
            ->setArguments([
                1 => new Reference('foo'),
                3 => ['bar'],
            ]);

        (new ResolveClassPass())->process($container);
        (new AutowirePass())->process($container);

        $definition = $container->getDefinition('multiple');
        $this->assertEquals(
            [
                new TypedReference(A::class, A::class),
                new Reference('foo'),
                new TypedReference(Dunglas::class, Dunglas::class),
                ['bar'],
            ],
            $definition->getArguments()
        );
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
     * @expectedExceptionMessage Cannot autowire service "arg_no_type_hint": argument "$bar" of method "Symfony\Component\DependencyInjection\Tests\Compiler\MultipleArguments::__construct()" is type-hinted "array", you should configure its value explicitly.
     */
    public function testScalarArgsCannotBeAutowired()
    {
        $container = new ContainerBuilder();

        $container->register(A::class);
        $container->register(Dunglas::class);
        $container->register('arg_no_type_hint', __NAMESPACE__.'\MultipleArguments')
            ->setArguments([1 => 'foo'])
            ->setAutowired(true);

        (new ResolveClassPass())->process($container);
        (new AutowirePass())->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
     * @expectedExceptionMessage Cannot autowire service "arg_no_type_hint": argument "$foo" of method "Symfony\Component\DependencyInjection\Tests\Compiler\MultipleArguments::__construct()" has no type-hint, you should configure its value explicitly.
     */
    public function testNoTypeArgsCannotBeAutowired()
    {
        $container = new ContainerBuilder();

        $container->register(A::class);
        $container->register(Dunglas::class);
        $container->register('arg_no_type_hint', __NAMESPACE__.'\MultipleArguments')
            ->setAutowired(true);

        (new ResolveClassPass())->process($container);
        (new AutowirePass())->process($container);
    }

    public function testOptionalScalarNotReallyOptionalUsesDefaultValue()
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

    public function testOptionalScalarArgsDontMessUpOrder()
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
            [
                new TypedReference(A::class, A::class),
                // use the default value
                'default_val',
                new TypedReference(Lille::class, Lille::class),
            ],
            $definition->getArguments()
        );
    }

    public function testOptionalScalarArgsNotPassedIfLast()
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
            [
                new TypedReference(A::class, A::class),
                new TypedReference(Lille::class, Lille::class),
            ],
            $definition->getArguments()
        );
    }

    public function testOptionalArgsNoRequiredForCoreClasses()
    {
        $container = new ContainerBuilder();

        $container->register('foo', \SplFileObject::class)
            ->addArgument('foo.txt')
            ->setAutowired(true);

        (new AutowirePass())->process($container);

        $definition = $container->getDefinition('foo');
        $this->assertEquals(
            ['foo.txt'],
            $definition->getArguments()
        );
    }

    public function testSetterInjection()
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
            ->addMethodCall('setWithCallsConfigured', ['manual_arg1', 'manual_arg2'])
        ;

        (new ResolveClassPass())->process($container);
        (new AutowireRequiredMethodsPass())->process($container);
        (new AutowirePass())->process($container);

        $methodCalls = $container->getDefinition('setter_injection')->getMethodCalls();

        $this->assertEquals(
            ['setWithCallsConfigured', 'setFoo', 'setDependencies', 'setChildMethodWithoutDocBlock'],
            array_column($methodCalls, 0)
        );

        // test setWithCallsConfigured args
        $this->assertEquals(
            ['manual_arg1', 'manual_arg2'],
            $methodCalls[0][1]
        );
        // test setFoo args
        $this->assertEquals(
            [new TypedReference(Foo::class, Foo::class)],
            $methodCalls[1][1]
        );
    }

    public function testExplicitMethodInjection()
    {
        $container = new ContainerBuilder();
        $container->register(Foo::class);
        $container->register(A::class);
        $container->register(CollisionA::class);
        $container->register(CollisionB::class);

        $container
            ->register('setter_injection', SetterInjection::class)
            ->setAutowired(true)
            ->addMethodCall('notASetter', [])
        ;

        (new ResolveClassPass())->process($container);
        (new AutowireRequiredMethodsPass())->process($container);
        (new AutowirePass())->process($container);

        $methodCalls = $container->getDefinition('setter_injection')->getMethodCalls();

        $this->assertEquals(
            ['notASetter', 'setFoo', 'setDependencies', 'setWithCallsConfigured', 'setChildMethodWithoutDocBlock'],
            array_column($methodCalls, 0)
        );
        $this->assertEquals(
            [new TypedReference(A::class, A::class)],
            $methodCalls[0][1]
        );
    }

    public function getCreateResourceTests()
    {
        return [
            ['IdenticalClassResource', true],
            ['ClassChangedConstructorArgs', false],
        ];
    }

    public function testIgnoreServiceWithClassNotExisting()
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

    public function testSetterInjectionCollisionThrowsException()
    {
        $container = new ContainerBuilder();

        $container->register('c1', CollisionA::class);
        $container->register('c2', CollisionB::class);
        $aDefinition = $container->register('setter_injection_collision', SetterInjectionCollision::class);
        $aDefinition->setAutowired(true);

        (new AutowireRequiredMethodsPass())->process($container);

        $pass = new AutowirePass();

        try {
            $pass->process($container);
        } catch (AutowiringFailedException $e) {
        }

        $this->assertNotNull($e);
        $this->assertSame('Cannot autowire service "setter_injection_collision": argument "$collision" of method "Symfony\Component\DependencyInjection\Tests\Compiler\SetterInjectionCollision::setMultipleInstancesForOneArg()" references interface "Symfony\Component\DependencyInjection\Tests\Compiler\CollisionInterface" but no such service exists. You should maybe alias this interface to one of these existing services: "c1", "c2".', $e->getMessage());
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
     * @expectedExceptionMessage Cannot autowire service "my_service": argument "$i" of method "Symfony\Component\DependencyInjection\Tests\Compiler\K::__construct()" references interface "Symfony\Component\DependencyInjection\Tests\Compiler\IInterface" but no such service exists. Did you create a class that implements this interface?
     */
    public function testInterfaceWithNoImplementationSuggestToWriteOne()
    {
        $container = new ContainerBuilder();

        $aDefinition = $container->register('my_service', K::class);
        $aDefinition->setAutowired(true);

        (new AutowireRequiredMethodsPass())->process($container);

        $pass = new AutowirePass();
        $pass->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
     * @expectedExceptionMessage Cannot autowire service "bar": argument "$foo" of method "Symfony\Component\DependencyInjection\Tests\Compiler\Bar::__construct()" references class "Symfony\Component\DependencyInjection\Tests\Compiler\Foo" but no such service exists. You should maybe alias this class to the existing "foo" service.
     */
    public function testProcessDoesNotTriggerDeprecations()
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

    public function testEmptyStringIsKept()
    {
        $container = new ContainerBuilder();

        $container->register(A::class);
        $container->register(Lille::class);
        $container->register('foo', __NAMESPACE__.'\MultipleArgumentsOptionalScalar')
            ->setAutowired(true)
            ->setArguments(['', '']);

        (new ResolveClassPass())->process($container);
        (new AutowirePass())->process($container);

        $this->assertEquals([new TypedReference(A::class, A::class), '', new TypedReference(Lille::class, Lille::class)], $container->getDefinition('foo')->getArguments());
    }

    public function testWithFactory()
    {
        $container = new ContainerBuilder();

        $container->register(Foo::class);
        $definition = $container->register('a', A::class)
            ->setFactory([A::class, 'create'])
            ->setAutowired(true);

        (new ResolveClassPass())->process($container);
        (new AutowirePass())->process($container);

        $this->assertEquals([new TypedReference(Foo::class, Foo::class)], $definition->getArguments());
    }

    /**
     * @dataProvider provideNotWireableCalls
     * @expectedException \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
     */
    public function testNotWireableCalls($method, $expectedMsg)
    {
        $container = new ContainerBuilder();

        $foo = $container->register('foo', NotWireable::class)->setAutowired(true)
            ->addMethodCall('setBar', [])
            ->addMethodCall('setOptionalNotAutowireable', [])
            ->addMethodCall('setOptionalNoTypeHint', [])
            ->addMethodCall('setOptionalArgNoAutowireable', [])
        ;

        if ($method) {
            $foo->addMethodCall($method, []);
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
        return [
            ['setNotAutowireable', 'Cannot autowire service "foo": argument "$n" of method "Symfony\Component\DependencyInjection\Tests\Compiler\NotWireable::setNotAutowireable()" has type "Symfony\Component\DependencyInjection\Tests\Compiler\NotARealClass" but this class was not found.'],
            ['setDifferentNamespace', 'Cannot autowire service "foo": argument "$n" of method "Symfony\Component\DependencyInjection\Tests\Compiler\NotWireable::setDifferentNamespace()" references class "stdClass" but no such service exists.'],
            [null, 'Invalid service "foo": method "Symfony\Component\DependencyInjection\Tests\Compiler\NotWireable::setProtectedMethod()" must be public.'],
        ];
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
     * @expectedExceptionMessage Cannot autowire service "foo": argument "$sam" of method "Symfony\Component\DependencyInjection\Tests\Compiler\NotWireable::setNotAutowireableBecauseOfATypo()" references class "Symfony\Component\DependencyInjection\Tests\Compiler\lesTilleuls" but no such service exists. Did you mean "Symfony\Component\DependencyInjection\Tests\Compiler\LesTilleuls"?
     */
    public function testSuggestRegisteredServicesWithSimilarCase()
    {
        $container = new ContainerBuilder();

        $container->register(LesTilleuls::class, LesTilleuls::class);
        $container->register('foo', NotWireable::class)->setAutowired(true)
            ->addMethodCall('setNotAutowireableBecauseOfATypo', [])
        ;

        (new ResolveClassPass())->process($container);
        (new AutowireRequiredMethodsPass())->process($container);
        (new AutowirePass())->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
     * @expectedExceptionMessage Cannot autowire service "j": argument "$i" of method "Symfony\Component\DependencyInjection\Tests\Compiler\J::__construct()" references class "Symfony\Component\DependencyInjection\Tests\Compiler\I" but no such service exists. Try changing the type-hint to "Symfony\Component\DependencyInjection\Tests\Compiler\IInterface" instead.
     */
    public function testByIdAlternative()
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
    public function testExceptionWhenAliasExists()
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
    public function testExceptionWhenAliasDoesNotExist()
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

    public function testInlineServicesAreNotCandidates()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(realpath(__DIR__.'/../Fixtures/xml')));
        $loader->load('services_inline_not_candidate.xml');

        $pass = new AutowirePass();
        $pass->process($container);

        $this->assertSame([], $container->getDefinition('autowired')->getArguments());
    }

    public function testAutowireDecorator()
    {
        $container = new ContainerBuilder();
        $container->register(LoggerInterface::class, NullLogger::class);
        $container->register(Decorated::class, Decorated::class);
        $container
            ->register(Decorator::class, Decorator::class)
            ->setDecoratedService(Decorated::class)
            ->setAutowired(true)
        ;

        (new DecoratorServicePass())->process($container);
        (new AutowirePass())->process($container);

        $definition = $container->getDefinition(Decorator::class);
        $this->assertSame(Decorator::class.'.inner', (string) $definition->getArgument(1));
    }

    public function testAutowireDecoratorChain()
    {
        $container = new ContainerBuilder();
        $container->register(LoggerInterface::class, NullLogger::class);
        $container->register(Decorated::class, Decorated::class);
        $container
            ->register(Decorator::class, Decorator::class)
            ->setDecoratedService(Decorated::class)
            ->setAutowired(true)
        ;
        $container
            ->register(DecoratedDecorator::class, DecoratedDecorator::class)
            ->setDecoratedService(Decorated::class)
            ->setAutowired(true)
        ;

        (new DecoratorServicePass())->process($container);
        (new AutowirePass())->process($container);

        $definition = $container->getDefinition(DecoratedDecorator::class);
        $this->assertSame(DecoratedDecorator::class.'.inner', (string) $definition->getArgument(0));
    }

    public function testAutowireDecoratorRenamedId()
    {
        $container = new ContainerBuilder();
        $container->register(LoggerInterface::class, NullLogger::class);
        $container->register(Decorated::class, Decorated::class);
        $container
            ->register(Decorator::class, Decorator::class)
            ->setDecoratedService(Decorated::class, 'renamed')
            ->setAutowired(true)
        ;

        (new DecoratorServicePass())->process($container);
        (new AutowirePass())->process($container);

        $definition = $container->getDefinition(Decorator::class);
        $this->assertSame('renamed', (string) $definition->getArgument(1));
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
     * @expectedExceptionMessage Cannot autowire service "Symfony\Component\DependencyInjection\Tests\Compiler\NonAutowirableDecorator": argument "$decorated1" of method "__construct()" references interface "Symfony\Component\DependencyInjection\Tests\Compiler\DecoratorInterface" but no such service exists. You should maybe alias this interface to one of these existing services: "Symfony\Component\DependencyInjection\Tests\Compiler\NonAutowirableDecorator", "Symfony\Component\DependencyInjection\Tests\Compiler\NonAutowirableDecorator.inner".
     */
    public function testDoNotAutowireDecoratorWhenSeveralArgumentOfTheType()
    {
        $container = new ContainerBuilder();
        $container->register(LoggerInterface::class, NullLogger::class);
        $container->register(Decorated::class, Decorated::class);
        $container
            ->register(NonAutowirableDecorator::class, NonAutowirableDecorator::class)
            ->setDecoratedService(Decorated::class)
            ->setAutowired(true)
        ;

        (new DecoratorServicePass())->process($container);
        (new AutowirePass())->process($container);
    }

    public function testErroredServiceLocator()
    {
        $container = new ContainerBuilder();
        $container->register('some_locator', 'stdClass')
            ->addArgument(new TypedReference(MissingClass::class, MissingClass::class, ContainerBuilder::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE))
            ->addTag('container.service_locator');

        (new AutowirePass())->process($container);

        $erroredDefinition = new Definition(MissingClass::class);

        $this->assertEquals($erroredDefinition->addError('Cannot autowire service "some_locator": it has type "Symfony\Component\DependencyInjection\Tests\Compiler\MissingClass" but this class was not found.'), $container->getDefinition('.errored.some_locator.'.MissingClass::class));
    }

    public function testNamedArgumentAliasResolveCollisions()
    {
        $container = new ContainerBuilder();

        $container->register('c1', CollisionA::class);
        $container->register('c2', CollisionB::class);
        $container->setAlias(CollisionInterface::class.' $collision', 'c2');
        $aDefinition = $container->register('setter_injection_collision', SetterInjectionCollision::class);
        $aDefinition->setAutowired(true);

        (new AutowireRequiredMethodsPass())->process($container);

        $pass = new AutowirePass();

        $pass->process($container);

        $expected = [
            [
                'setMultipleInstancesForOneArg',
                [new TypedReference(CollisionInterface::class.' $collision', CollisionInterface::class)],
            ],
        ];
        $this->assertEquals($expected, $container->getDefinition('setter_injection_collision')->getMethodCalls());
    }
}
