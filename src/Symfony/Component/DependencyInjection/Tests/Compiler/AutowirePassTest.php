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

use Symfony\Component\DependencyInjection\Compiler\AutowirePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Tests\Fixtures\includes\FooVariadic;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class AutowirePassTest extends \PHPUnit_Framework_TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();

        $container->register('foo', __NAMESPACE__.'\Foo');
        $barDefinition = $container->register('bar', __NAMESPACE__.'\Bar');
        $barDefinition->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);

        $this->assertCount(1, $container->getDefinition('bar')->getArguments());
        $this->assertEquals('foo', (string) $container->getDefinition('bar')->getArgument(0));
    }

    /**
     * @requires PHP 5.6
     */
    public function testProcessVariadic()
    {
        $container = new ContainerBuilder();
        $container->register('foo', Foo::class);
        $definition = $container->register('fooVariadic', FooVariadic::class);
        $definition->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);

        $this->assertCount(1, $container->getDefinition('fooVariadic')->getArguments());
        $this->assertEquals('foo', (string) $container->getDefinition('fooVariadic')->getArgument(0));
    }

    public function testProcessAutowireParent()
    {
        $container = new ContainerBuilder();

        $container->register('b', __NAMESPACE__.'\B');
        $cDefinition = $container->register('c', __NAMESPACE__.'\C');
        $cDefinition->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);

        $this->assertCount(1, $container->getDefinition('c')->getArguments());
        $this->assertEquals('b', (string) $container->getDefinition('c')->getArgument(0));
    }

    public function testProcessAutowireInterface()
    {
        $container = new ContainerBuilder();

        $container->register('f', __NAMESPACE__.'\F');
        $gDefinition = $container->register('g', __NAMESPACE__.'\G');
        $gDefinition->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);

        $this->assertCount(3, $container->getDefinition('g')->getArguments());
        $this->assertEquals('f', (string) $container->getDefinition('g')->getArgument(0));
        $this->assertEquals('f', (string) $container->getDefinition('g')->getArgument(1));
        $this->assertEquals('f', (string) $container->getDefinition('g')->getArgument(2));
    }

    public function testCompleteExistingDefinition()
    {
        $container = new ContainerBuilder();

        $container->register('b', __NAMESPACE__.'\B');
        $container->register('f', __NAMESPACE__.'\F');
        $hDefinition = $container->register('h', __NAMESPACE__.'\H')->addArgument(new Reference('b'));
        $hDefinition->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);

        $this->assertCount(2, $container->getDefinition('h')->getArguments());
        $this->assertEquals('b', (string) $container->getDefinition('h')->getArgument(0));
        $this->assertEquals('f', (string) $container->getDefinition('h')->getArgument(1));
    }

    public function testCompleteExistingDefinitionWithNotDefinedArguments()
    {
        $container = new ContainerBuilder();

        $container->register('b', __NAMESPACE__.'\B');
        $container->register('f', __NAMESPACE__.'\F');
        $hDefinition = $container->register('h', __NAMESPACE__.'\H')->addArgument('')->addArgument('');
        $hDefinition->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);

        $this->assertCount(2, $container->getDefinition('h')->getArguments());
        $this->assertEquals('b', (string) $container->getDefinition('h')->getArgument(0));
        $this->assertEquals('f', (string) $container->getDefinition('h')->getArgument(1));
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Unable to autowire argument of type "Symfony\Component\DependencyInjection\Tests\Compiler\CollisionInterface" for the service "a". Multiple services exist for this interface (c1, c2, c3).
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
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Unable to autowire argument of type "Symfony\Component\DependencyInjection\Tests\Compiler\Foo" for the service "a". Multiple services exist for this class (a1, a2).
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
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Unable to autowire argument of type "Symfony\Component\DependencyInjection\Tests\Compiler\A" for the service "a". Multiple services exist for this class (a1, a2).
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
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Unable to autowire argument of type "Symfony\Component\DependencyInjection\Tests\Compiler\CollisionInterface" for the service "a". No services were found matching this interface and it cannot be auto-registered.
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

        $this->assertCount(1, $container->getDefinition('coop_tilleuls')->getArguments());
        $this->assertEquals('autowired.Symfony\Component\DependencyInjection\Tests\Compiler\Dunglas', $container->getDefinition('coop_tilleuls')->getArgument(0));

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

        $container->setParameter('class_name', __NAMESPACE__.'\Foo');
        $container->register('foo', '%class_name%');
        $barDefinition = $container->register('bar', __NAMESPACE__.'\Bar');
        $barDefinition->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);

        $this->assertEquals('foo', $container->getDefinition('bar')->getArgument(0));
    }

    public function testOptionalParameter()
    {
        $container = new ContainerBuilder();

        $container->register('a', __NAMESPACE__.'\A');
        $container->register('foo', __NAMESPACE__.'\Foo');
        $optDefinition = $container->register('opt', __NAMESPACE__.'\OptionalParameter');
        $optDefinition->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);

        $definition = $container->getDefinition('opt');
        $this->assertNull($definition->getArgument(0));
        $this->assertEquals('a', $definition->getArgument(1));
        $this->assertEquals('foo', $definition->getArgument(2));
    }

    public function testDontTriggerAutowiring()
    {
        $container = new ContainerBuilder();

        $container->register('foo', __NAMESPACE__.'\Foo');
        $container->register('bar', __NAMESPACE__.'\Bar');

        $pass = new AutowirePass();
        $pass->process($container);

        $this->assertCount(0, $container->getDefinition('bar')->getArguments());
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Cannot autowire argument $r of method Symfony\Component\DependencyInjection\Tests\Compiler\BadTypeHintedArgument::__construct() for service "a": Class Symfony\Component\DependencyInjection\Tests\Compiler\NotARealClass does not exist.
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
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Cannot autowire argument $r of method Symfony\Component\DependencyInjection\Tests\Compiler\BadParentTypeHintedArgument::__construct() for service "a": Class Symfony\Component\DependencyInjection\Tests\Compiler\OptionalServiceClass does not exist.
     */
    public function testParentClassNotFoundThrowsException()
    {
        $container = new ContainerBuilder();

        $aDefinition = $container->register('a', __NAMESPACE__.'\BadParentTypeHintedArgument');
        $aDefinition->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);
    }

    public function testDontUseAbstractServices()
    {
        $container = new ContainerBuilder();

        $container->register('abstract_foo', __NAMESPACE__.'\Foo')->setAbstract(true);
        $container->register('foo', __NAMESPACE__.'\Foo');
        $container->register('bar', __NAMESPACE__.'\Bar')->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);

        $arguments = $container->getDefinition('bar')->getArguments();
        $this->assertSame('foo', (string) $arguments[0]);
    }

    public function testSomeSpecificArgumentsAreSet()
    {
        $container = new ContainerBuilder();

        $container->register('foo', __NAMESPACE__.'\Foo');
        $container->register('a', __NAMESPACE__.'\A');
        $container->register('dunglas', __NAMESPACE__.'\Dunglas');
        $container->register('multiple', __NAMESPACE__.'\MultipleArguments')
            ->setAutowired(true)
            // set the 2nd (index 1) argument only: autowire the first and third
            // args are: A, Foo, Dunglas
            ->setArguments(array(
                1 => new Reference('foo'),
            ));

        $pass = new AutowirePass();
        $pass->process($container);

        $definition = $container->getDefinition('multiple');
        $this->assertEquals(
            array(
                new Reference('a'),
                new Reference('foo'),
                new Reference('dunglas'),
            ),
            $definition->getArguments()
        );
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Cannot autowire service "arg_no_type_hint": argument $foo of method Symfony\Component\DependencyInjection\Tests\Compiler\MultipleArguments::__construct() must have a type-hint or be given a value explicitly.
     */
    public function testScalarArgsCannotBeAutowired()
    {
        $container = new ContainerBuilder();

        $container->register('a', __NAMESPACE__.'\A');
        $container->register('dunglas', __NAMESPACE__.'\Dunglas');
        $container->register('arg_no_type_hint', __NAMESPACE__.'\MultipleArguments')
            ->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);

        $container->getDefinition('arg_no_type_hint');
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Cannot autowire service "not_really_optional_scalar": argument $foo of method Symfony\Component\DependencyInjection\Tests\Compiler\MultipleArgumentsOptionalScalarNotReallyOptional::__construct() must have a type-hint or be given a value explicitly.
     */
    public function testOptionalScalarNotReallyOptionalThrowException()
    {
        $container = new ContainerBuilder();

        $container->register('a', __NAMESPACE__.'\A');
        $container->register('lille', __NAMESPACE__.'\Lille');
        $container->register('not_really_optional_scalar', __NAMESPACE__.'\MultipleArgumentsOptionalScalarNotReallyOptional')
            ->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);
    }

    public function testOptionalScalarArgsDontMessUpOrder()
    {
        $container = new ContainerBuilder();

        $container->register('a', __NAMESPACE__.'\A');
        $container->register('lille', __NAMESPACE__.'\Lille');
        $container->register('with_optional_scalar', __NAMESPACE__.'\MultipleArgumentsOptionalScalar')
            ->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);

        $definition = $container->getDefinition('with_optional_scalar');
        $this->assertEquals(
            array(
                new Reference('a'),
                // use the default value
                'default_val',
                new Reference('lille'),
            ),
            $definition->getArguments()
        );
    }

    public function testOptionalScalarArgsNotPassedIfLast()
    {
        $container = new ContainerBuilder();

        $container->register('a', __NAMESPACE__.'\A');
        $container->register('lille', __NAMESPACE__.'\Lille');
        $container->register('with_optional_scalar_last', __NAMESPACE__.'\MultipleArgumentsOptionalScalarLast')
            ->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);

        $definition = $container->getDefinition('with_optional_scalar_last');
        $this->assertEquals(
            array(
                new Reference('a'),
                new Reference('lille'),
                // third arg shouldn't *need* to be passed
                // but that's hard to "pull of" with autowiring, so
                // this assumes passing the default val is ok
                'some_val',
            ),
            $definition->getArguments()
        );
    }

    public function testSetterInjection()
    {
        $container = new ContainerBuilder();
        $container->register('app_foo', Foo::class);
        $container->register('app_a', A::class);
        $container->register('app_collision_a', CollisionA::class);
        $container->register('app_collision_b', CollisionB::class);

        // manually configure *one* call, to override autowiring
        $container
            ->register('setter_injection', SetterInjection::class)
            ->setAutowiredMethods(array('set*'))
            ->addMethodCall('setWithCallsConfigured', array('manual_arg1', 'manual_arg2'))
        ;

        $pass = new AutowirePass();
        $pass->process($container);

        $methodCalls = $container->getDefinition('setter_injection')->getMethodCalls();

        // grab the call method names
        $actualMethodNameCalls = array_map(function ($call) {
            return $call[0];
        }, $methodCalls);
        $this->assertEquals(
            array('setWithCallsConfigured', 'setFoo', 'setDependencies'),
            $actualMethodNameCalls
        );

        // test setWithCallsConfigured args
        $this->assertEquals(
            array('manual_arg1', 'manual_arg2'),
            $methodCalls[0][1]
        );
        // test setFoo args
        $this->assertEquals(
            array(new Reference('app_foo')),
            $methodCalls[1][1]
        );
    }

    public function testExplicitMethodInjection()
    {
        $container = new ContainerBuilder();
        $container->register('app_foo', Foo::class);
        $container->register('app_a', A::class);
        $container->register('app_collision_a', CollisionA::class);
        $container->register('app_collision_b', CollisionB::class);

        $container
            ->register('setter_injection', SetterInjection::class)
            ->setAutowiredMethods(array('setFoo', 'notASetter'))
        ;

        $pass = new AutowirePass();
        $pass->process($container);

        $methodCalls = $container->getDefinition('setter_injection')->getMethodCalls();

        $actualMethodNameCalls = array_map(function ($call) {
            return $call[0];
        }, $methodCalls);
        $this->assertEquals(
            array('setFoo', 'notASetter'),
            $actualMethodNameCalls
        );
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
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Unable to autowire argument of type "Symfony\Component\DependencyInjection\Tests\Compiler\CollisionInterface" for the service "setter_injection_collision". Multiple services exist for this interface (c1, c2).
     * @expectedExceptionCode 1
     */
    public function testSetterInjectionCollisionThrowsException()
    {
        $container = new ContainerBuilder();

        $container->register('c1', CollisionA::class);
        $container->register('c2', CollisionB::class);
        $aDefinition = $container->register('setter_injection_collision', SetterInjectionCollision::class);
        $aDefinition->setAutowiredMethods(array('set*'));

        $pass = new AutowirePass();
        $pass->process($container);
    }

    public function testLogUnusedPatterns()
    {
        $container = new ContainerBuilder();

        $definition = $container->register('foo', Foo::class);
        $definition->setAutowiredMethods(array('not', 'exist*'));

        $pass = new AutowirePass();
        $pass->process($container);

        $this->assertEquals(array(AutowirePass::class.': Autowiring\'s patterns "not", "exist*" for service "foo" don\'t match any method.'), $container->getCompiler()->getLog());
    }

    public function testPartialMethodCalls()
    {
        $container = new ContainerBuilder();

        $container->register('a', A::class);
        $container->register('foo', Foo::class);
        $definition = $container->register('bar', SetterInjection::class);
        $definition->setAutowired(true);
        $definition->addMethodCall('setDependencies', array(new Reference('foo')));
    }

    public function testEmptyStringIsKept()
    {
        $container = new ContainerBuilder();

        $container->register('a', __NAMESPACE__.'\A');
        $container->register('lille', __NAMESPACE__.'\Lille');
        $container->register('foo', __NAMESPACE__.'\MultipleArgumentsOptionalScalar')
            ->setAutowired(true)
            ->setArguments(array('', ''));

        $pass = new AutowirePass();
        $pass->process($container);

        $this->assertEquals(array(new Reference('a'), '', new Reference('lille')), $container->getDefinition('foo')->getArguments());
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

class A
{
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
    public function __construct(Dunglas $k)
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

class SetterInjection
{
    public function setFoo(Foo $foo)
    {
        // should be called
    }

    public function setDependencies(Foo $foo, A $a)
    {
        // should be called
    }

    public function setBar()
    {
        // should not be called
    }

    public function setNotAutowireable(NotARealClass $n)
    {
        // should not be called
    }

    public function setArgCannotAutowire($foo)
    {
        // should not be called
    }

    public function setOptionalNotAutowireable(NotARealClass $n = null)
    {
        // should not be called
    }

    public function setOptionalNoTypeHint($foo = null)
    {
        // should not be called
    }

    public function setOptionalArgNoAutowireable($other = 'default_val')
    {
        // should not be called
    }

    public function setWithCallsConfigured(A $a)
    {
        // this method has a calls configured on it
        // should not be called
    }

    public function notASetter(A $a)
    {
        // should be called only when explicitly specified
    }
}

class SetterInjectionCollision
{
    public function setMultipleInstancesForOneArg(CollisionInterface $collision)
    {
        // The CollisionInterface cannot be autowired - there are multiple

        // should throw an exception
    }
}
