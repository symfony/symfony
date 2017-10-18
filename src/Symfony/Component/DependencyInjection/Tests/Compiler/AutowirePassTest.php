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
use Symfony\Component\DependencyInjection\Compiler\AutowirePass;
use Symfony\Component\DependencyInjection\Compiler\ResolveClassPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Tests\Fixtures\includes\FooVariadic;
use Symfony\Component\DependencyInjection\TypedReference;

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

    /**
     * @requires PHP 5.6
     */
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
     * @group legacy
     * @expectedDeprecation Autowiring services based on the types they implement is deprecated since Symfony 3.3 and won't be supported in version 4.0. You should alias the "Symfony\Component\DependencyInjection\Tests\Compiler\B" service to "Symfony\Component\DependencyInjection\Tests\Compiler\A" instead.
     * @expectedExceptionInSymfony4 \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessageInSymfony4 Cannot autowire service "c": argument "$a" of method "Symfony\Component\DependencyInjection\Tests\Compiler\C::__construct()" references class "Symfony\Component\DependencyInjection\Tests\Compiler\A" but no such service exists. You should maybe alias this class to the existing "Symfony\Component\DependencyInjection\Tests\Compiler\B" service.
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
     * @group legacy
     * @expectedDeprecation Autowiring services based on the types they implement is deprecated since Symfony 3.3 and won't be supported in version 4.0. Try changing the type-hint for argument "$a" of method "Symfony\Component\DependencyInjection\Tests\Compiler\C::__construct()" to "Symfony\Component\DependencyInjection\Tests\Compiler\AInterface" instead.
     * @expectedExceptionInSymfony4 \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessageInSymfony4 Cannot autowire service "c": argument "$a" of method "Symfony\Component\DependencyInjection\Tests\Compiler\C::__construct()" references class "Symfony\Component\DependencyInjection\Tests\Compiler\A" but no such service exists. You should maybe alias this class to the existing "Symfony\Component\DependencyInjection\Tests\Compiler\B" service.
     */
    public function testProcessLegacyAutowireWithAvailableInterface()
    {
        $container = new ContainerBuilder();

        $container->setAlias(AInterface::class, B::class);
        $container->register(B::class);
        $cDefinition = $container->register('c', __NAMESPACE__.'\C');
        $cDefinition->setAutowired(true);

        (new ResolveClassPass())->process($container);
        (new AutowirePass())->process($container);

        $this->assertCount(1, $container->getDefinition('c')->getArguments());
        $this->assertEquals(B::class, (string) $container->getDefinition('c')->getArgument(0));
    }

    /**
     * @group legacy
     * @expectedDeprecation Autowiring services based on the types they implement is deprecated since Symfony 3.3 and won't be supported in version 4.0. You should alias the "Symfony\Component\DependencyInjection\Tests\Compiler\F" service to "Symfony\Component\DependencyInjection\Tests\Compiler\DInterface" instead.
     * @expectedExceptionInSymfony4 \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessageInSymfony4 Cannot autowire service "g": argument "$d" of method "Symfony\Component\DependencyInjection\Tests\Compiler\G::__construct()" references interface "Symfony\Component\DependencyInjection\Tests\Compiler\DInterface" but no such service exists. You should maybe alias this interface to the existing "Symfony\Component\DependencyInjection\Tests\Compiler\F" service.
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

    public function testExceptionsAreStored()
    {
        $container = new ContainerBuilder();

        $container->register('c1', __NAMESPACE__.'\CollisionA');
        $container->register('c2', __NAMESPACE__.'\CollisionB');
        $container->register('c3', __NAMESPACE__.'\CollisionB');
        $aDefinition = $container->register('a', __NAMESPACE__.'\CannotBeAutowired');
        $aDefinition->setAutowired(true);

        $pass = new AutowirePass(false);
        $pass->process($container);
        $this->assertCount(1, $pass->getAutowiringExceptions());
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
     * @expectedExceptionMessage Unable to resolve service "private_service": constructor of class "Symfony\Component\DependencyInjection\Tests\Compiler\PrivateConstructor" must be public.
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

    public function testCreateDefinition()
    {
        $container = new ContainerBuilder();

        $coopTilleulsDefinition = $container->register('coop_tilleuls', __NAMESPACE__.'\LesTilleuls');
        $coopTilleulsDefinition->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);

        $this->assertCount(2, $container->getDefinition('coop_tilleuls')->getArguments());
        $this->assertEquals('autowired.Symfony\Component\DependencyInjection\Tests\Compiler\Dunglas', $container->getDefinition('coop_tilleuls')->getArgument(0));
        $this->assertEquals('autowired.Symfony\Component\DependencyInjection\Tests\Compiler\Dunglas', $container->getDefinition('coop_tilleuls')->getArgument(1));

        $dunglasDefinition = $container->getDefinition('autowired.Symfony\Component\DependencyInjection\Tests\Compiler\Dunglas');
        $this->assertEquals(__NAMESPACE__.'\Dunglas', $dunglasDefinition->getClass());
        $this->assertFalse($dunglasDefinition->isPublic());
        $this->assertCount(1, $dunglasDefinition->getArguments());
        $this->assertEquals('autowired.Symfony\Component\DependencyInjection\Tests\Compiler\Lille', $dunglasDefinition->getArgument(0));

        $lilleDefinition = $container->getDefinition('autowired.Symfony\Component\DependencyInjection\Tests\Compiler\Lille');
        $this->assertEquals(__NAMESPACE__.'\Lille', $lilleDefinition->getClass());
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

        $pass = new AutowirePass();
        $pass->process($container);
    }

    /**
     * @group legacy
     * @expectedDeprecation Autowiring services based on the types they implement is deprecated since Symfony 3.3 and won't be supported in version 4.0. You should rename (or alias) the "foo" service to "Symfony\Component\DependencyInjection\Tests\Compiler\Foo" instead.
     * @expectedExceptionInSymfony4 \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
     * @expectedExceptionMessageInSymfony4 Cannot autowire service "bar": argument "$foo" of method "Symfony\Component\DependencyInjection\Tests\Compiler\Bar::__construct()" references class "Symfony\Component\DependencyInjection\Tests\Compiler\Foo" but this service is abstract. You should maybe alias this class to the existing "foo" service.
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
    public function testScalarArgsCannotBeAutowired()
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
            array(
                new TypedReference(A::class, A::class, MultipleArgumentsOptionalScalar::class),
                // use the default value
                'default_val',
                new TypedReference(Lille::class, Lille::class),
            ),
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
            array(
                new TypedReference(A::class, A::class, MultipleArgumentsOptionalScalarLast::class),
                new TypedReference(Lille::class, Lille::class, MultipleArgumentsOptionalScalarLast::class),
            ),
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
            array('foo.txt'),
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
            ->addMethodCall('setWithCallsConfigured', array('manual_arg1', 'manual_arg2'))
        ;

        (new ResolveClassPass())->process($container);
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
            ->addMethodCall('notASetter', array())
        ;

        (new ResolveClassPass())->process($container);
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

    public function testTypedReference()
    {
        $container = new ContainerBuilder();

        $container
            ->register('bar', Bar::class)
            ->setProperty('a', array(new TypedReference(A::class, A::class, Bar::class)))
        ;

        $pass = new AutowirePass();
        $pass->process($container);

        $this->assertSame(A::class, $container->getDefinition('autowired.'.A::class)->getClass());
    }

    /**
     * @dataProvider getCreateResourceTests
     * @group legacy
     */
    public function testCreateResourceForClass($className, $isEqual)
    {
        $startingResource = AutowirePass::createResourceForClass(
            new \ReflectionClass(__NAMESPACE__.'\ClassForResource')
        );
        $newResource = AutowirePass::createResourceForClass(
            new \ReflectionClass(__NAMESPACE__.'\\'.$className)
        );

        // hack so the objects don't differ by the class name
        $startingReflObject = new \ReflectionObject($startingResource);
        $reflProp = $startingReflObject->getProperty('class');
        $reflProp->setAccessible(true);
        $reflProp->setValue($startingResource, __NAMESPACE__.'\\'.$className);

        if ($isEqual) {
            $this->assertEquals($startingResource, $newResource);
        } else {
            $this->assertNotEquals($startingResource, $newResource);
        }
    }

    public function getCreateResourceTests()
    {
        return array(
            array('IdenticalClassResource', true),
            array('ClassChangedConstructorArgs', false),
        );
    }

    public function testIgnoreServiceWithClassNotExisting()
    {
        $container = new ContainerBuilder();

        $container->register('class_not_exist', __NAMESPACE__.'\OptionalServiceClass');

        $barDefinition = $container->register('bar', __NAMESPACE__.'\Bar');
        $barDefinition->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);

        $this->assertTrue($container->hasDefinition('bar'));
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
     * @expectedExceptionMessage Cannot autowire service "setter_injection_collision": argument "$collision" of method "Symfony\Component\DependencyInjection\Tests\Compiler\SetterInjectionCollision::setMultipleInstancesForOneArg()" references interface "Symfony\Component\DependencyInjection\Tests\Compiler\CollisionInterface" but no such service exists. You should maybe alias this interface to one of these existing services: "c1", "c2".
     */
    public function testSetterInjectionCollisionThrowsException()
    {
        $container = new ContainerBuilder();

        $container->register('c1', CollisionA::class);
        $container->register('c2', CollisionB::class);
        $aDefinition = $container->register('setter_injection_collision', SetterInjectionCollision::class);
        $aDefinition->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);
    }

    /**
     * @group legacy
     * @expectedDeprecation Autowiring services based on the types they implement is deprecated since Symfony 3.3 and won't be supported in version 4.0. You should rename (or alias) the "foo" service to "Symfony\Component\DependencyInjection\Tests\Compiler\Foo" instead.
     * @expectedExceptionInSymfony4 \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
     * @expectedExceptionMessageInSymfony4 Cannot autowire service "bar": argument "$foo" of method "Symfony\Component\DependencyInjection\Tests\Compiler\Bar::__construct()" references class "Symfony\Component\DependencyInjection\Tests\Compiler\Foo" but no such service exists. You should maybe alias this class to the existing "foo" service.
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
            ->setArguments(array('', ''));

        (new ResolveClassPass())->process($container);
        (new AutowirePass())->process($container);

        $this->assertEquals(array(new TypedReference(A::class, A::class, MultipleArgumentsOptionalScalar::class), '', new TypedReference(Lille::class, Lille::class)), $container->getDefinition('foo')->getArguments());
    }

    public function testWithFactory()
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
    public function testNotWireableCalls($method, $expectedMsg)
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
        (new AutowirePass())->process($container);
    }

    public function provideNotWireableCalls()
    {
        return array(
            array('setNotAutowireable', 'Cannot autowire service "foo": argument "$n" of method "Symfony\Component\DependencyInjection\Tests\Compiler\NotWireable::setNotAutowireable()" has type "Symfony\Component\DependencyInjection\Tests\Compiler\NotARealClass" but this class was not found.'),
            array('setDifferentNamespace', 'Cannot autowire service "foo": argument "$n" of method "Symfony\Component\DependencyInjection\Tests\Compiler\NotWireable::setDifferentNamespace()" references class "stdClass" but no such service exists. It cannot be auto-registered because it is from a different root namespace.'),
            array(null, 'Cannot autowire service "foo": method "Symfony\Component\DependencyInjection\Tests\Compiler\NotWireable::setProtectedMethod()" must be public.'),
        );
    }

    /**
     * @group legacy
     * @expectedDeprecation Autowiring services based on the types they implement is deprecated since Symfony 3.3 and won't be supported in version 4.0. Try changing the type-hint for argument "$i" of method "Symfony\Component\DependencyInjection\Tests\Compiler\J::__construct()" to "Symfony\Component\DependencyInjection\Tests\Compiler\IInterface" instead.
     * @expectedExceptionInSymfony4 \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
     * @expectedExceptionMessageInSymfony4 Cannot autowire service "j": argument "$i" of method "Symfony\Component\DependencyInjection\Tests\Compiler\J::__construct()" references class "Symfony\Component\DependencyInjection\Tests\Compiler\I" but no such service exists. Try changing the type-hint to "Symfony\Component\DependencyInjection\Tests\Compiler\IInterface" instead.
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
     * @group legacy
     * @expectedDeprecation Autowiring services based on the types they implement is deprecated since Symfony 3.3 and won't be supported in version 4.0. Try changing the type-hint for "Symfony\Component\DependencyInjection\Tests\Compiler\A" in "Symfony\Component\DependencyInjection\Tests\Compiler\Bar" to "Symfony\Component\DependencyInjection\Tests\Compiler\AInterface" instead.
     */
    public function testTypedReferenceDeprecationNotice()
    {
        $container = new ContainerBuilder();

        $container->register('aClass', A::class);
        $container->setAlias(AInterface::class, 'aClass');
        $container
            ->register('bar', Bar::class)
            ->setProperty('a', array(new TypedReference(A::class, A::class, Bar::class)))
        ;

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
}

class Foo
{
}

class Bar
{
    public function __construct(Foo $foo)
    {
    }
}

interface AInterface
{
}

class A implements AInterface
{
    public static function create(Foo $foo)
    {
    }
}

class B extends A
{
}

class C
{
    public function __construct(A $a)
    {
    }
}

interface DInterface
{
}

interface EInterface extends DInterface
{
}

interface IInterface
{
}

class I implements IInterface
{
}

class F extends I implements EInterface
{
}

class G
{
    public function __construct(DInterface $d, EInterface $e, IInterface $i)
    {
    }
}

class H
{
    public function __construct(B $b, DInterface $d)
    {
    }
}

class D
{
    public function __construct(A $a, DInterface $d)
    {
    }
}

class E
{
    public function __construct(D $d = null)
    {
    }
}

class J
{
    public function __construct(I $i)
    {
    }
}

interface CollisionInterface
{
}

class CollisionA implements CollisionInterface
{
}

class CollisionB implements CollisionInterface
{
}

class CannotBeAutowired
{
    public function __construct(CollisionInterface $collision)
    {
    }
}

class CannotBeAutowiredForwardOrder
{
    public function __construct(CollisionA $a, CollisionInterface $b, CollisionB $c)
    {
    }
}

class CannotBeAutowiredReverseOrder
{
    public function __construct(CollisionA $a, CollisionB $c, CollisionInterface $b)
    {
    }
}

class Lille
{
}

class Dunglas
{
    public function __construct(Lille $l)
    {
    }
}

class LesTilleuls
{
    public function __construct(Dunglas $j, Dunglas $k)
    {
    }
}

class OptionalParameter
{
    public function __construct(CollisionInterface $c = null, A $a, Foo $f = null)
    {
    }
}

class BadTypeHintedArgument
{
    public function __construct(Dunglas $k, NotARealClass $r)
    {
    }
}
class BadParentTypeHintedArgument
{
    public function __construct(Dunglas $k, OptionalServiceClass $r)
    {
    }
}
class NotGuessableArgument
{
    public function __construct(Foo $k)
    {
    }
}
class NotGuessableArgumentForSubclass
{
    public function __construct(A $k)
    {
    }
}
class MultipleArguments
{
    public function __construct(A $k, $foo, Dunglas $dunglas)
    {
    }
}

class MultipleArgumentsOptionalScalar
{
    public function __construct(A $a, $foo = 'default_val', Lille $lille = null)
    {
    }
}
class MultipleArgumentsOptionalScalarLast
{
    public function __construct(A $a, Lille $lille, $foo = 'some_val')
    {
    }
}
class MultipleArgumentsOptionalScalarNotReallyOptional
{
    public function __construct(A $a, $foo = 'default_val', Lille $lille)
    {
    }
}

/*
 * Classes used for testing createResourceForClass
 */
class ClassForResource
{
    public function __construct($foo, Bar $bar = null)
    {
    }

    public function setBar(Bar $bar)
    {
    }
}
class IdenticalClassResource extends ClassForResource
{
}

class ClassChangedConstructorArgs extends ClassForResource
{
    public function __construct($foo, Bar $bar, $baz)
    {
    }
}

class SetterInjection extends SetterInjectionParent
{
    /**
     * @required
     */
    public function setFoo(Foo $foo)
    {
        // should be called
    }

    /** @inheritdoc*/
    public function setDependencies(Foo $foo, A $a)
    {
        // should be called
    }

    /** {@inheritdoc} */
    public function setWithCallsConfigured(A $a)
    {
        // this method has a calls configured on it
    }

    public function notASetter(A $a)
    {
        // should be called only when explicitly specified
    }

    /**
     * @required*/
    public function setChildMethodWithoutDocBlock(A $a)
    {
    }
}

class SetterInjectionParent
{
    /** @required*/
    public function setDependencies(Foo $foo, A $a)
    {
        // should be called
    }

    public function notASetter(A $a)
    {
        // @required should be ignored when the child does not add @inheritdoc
    }

    /**	@required <tab> prefix is on purpose */
    public function setWithCallsConfigured(A $a)
    {
    }

    /** @required */
    public function setChildMethodWithoutDocBlock(A $a)
    {
    }
}

class SetterInjectionCollision
{
    /**
     * @required
     */
    public function setMultipleInstancesForOneArg(CollisionInterface $collision)
    {
        // The CollisionInterface cannot be autowired - there are multiple

        // should throw an exception
    }
}

class NotWireable
{
    public function setNotAutowireable(NotARealClass $n)
    {
    }

    public function setBar()
    {
    }

    public function setOptionalNotAutowireable(NotARealClass $n = null)
    {
    }

    public function setDifferentNamespace(\stdClass $n)
    {
    }

    public function setOptionalNoTypeHint($foo = null)
    {
    }

    public function setOptionalArgNoAutowireable($other = 'default_val')
    {
    }

    /** @required */
    protected function setProtectedMethod(A $a)
    {
    }
}

class PrivateConstructor
{
    private function __construct()
    {
    }
}
