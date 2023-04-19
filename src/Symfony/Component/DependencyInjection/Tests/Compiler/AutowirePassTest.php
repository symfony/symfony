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
use Symfony\Bridge\PhpUnit\ClassExistsMock;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\ClassExistenceResource;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Compiler\AutowireAsDecoratorPass;
use Symfony\Component\DependencyInjection\Compiler\AutowirePass;
use Symfony\Component\DependencyInjection\Compiler\AutowireRequiredMethodsPass;
use Symfony\Component\DependencyInjection\Compiler\DecoratorServicePass;
use Symfony\Component\DependencyInjection\Compiler\ResolveClassPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\AutowiringFailedException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Tests\Fixtures\BarInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\CaseSensitiveClass;
use Symfony\Component\DependencyInjection\Tests\Fixtures\includes\FooVariadic;
use Symfony\Component\DependencyInjection\Tests\Fixtures\WithTarget;
use Symfony\Component\DependencyInjection\TypedReference;
use Symfony\Component\ExpressionLanguage\Expression;

require_once __DIR__.'/../Fixtures/includes/autowiring_classes.php';

class AutowirePassTest extends TestCase
{
    use ExpectDeprecationTrait;

    public static function setUpBeforeClass(): void
    {
        ClassExistsMock::register(AutowirePass::class);
    }

    public function testProcess()
    {
        $container = new ContainerBuilder();

        $container->register(Foo::class);
        $barDefinition = $container->register('bar', Bar::class);
        $barDefinition->setAutowired(true);

        (new ResolveClassPass())->process($container);
        (new AutowirePass())->process($container);

        $this->assertCount(1, $container->getDefinition('bar')->getArguments());
        $this->assertEquals(Foo::class, (string) $container->getDefinition('bar')->getArgument(0));
    }

    public function testProcessNotExistingActionParam()
    {
        $container = new ContainerBuilder();

        $container->register(Foo::class);
        $barDefinition = $container->register(ElsaAction::class, ElsaAction::class);
        $barDefinition->setAutowired(true);

        (new ResolveClassPass())->process($container);
        try {
            (new AutowirePass())->process($container);
            $this->fail('AutowirePass should have thrown an exception');
        } catch (AutowiringFailedException $e) {
            $this->assertSame('Cannot autowire service "Symfony\Component\DependencyInjection\Tests\Compiler\ElsaAction": argument "$notExisting" of method "__construct()" has type "Symfony\Component\DependencyInjection\Tests\Compiler\NotExisting" but this class was not found.', (string) $e->getMessage());
        }
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

    public function testProcessAutowireParent()
    {
        $container = new ContainerBuilder();

        $container->register(B::class);
        $cDefinition = $container->register('c', C::class);
        $cDefinition->setAutowired(true);

        (new ResolveClassPass())->process($container);
        try {
            (new AutowirePass())->process($container);
            $this->fail('AutowirePass should have thrown an exception');
        } catch (AutowiringFailedException $e) {
            $this->assertSame('Cannot autowire service "c": argument "$a" of method "Symfony\Component\DependencyInjection\Tests\Compiler\C::__construct()" references class "Symfony\Component\DependencyInjection\Tests\Compiler\A" but no such service exists. You should maybe alias this class to the existing "Symfony\Component\DependencyInjection\Tests\Compiler\B" service.', (string) $e->getMessage());
        }
    }

    public function testProcessAutowireInterface()
    {
        $container = new ContainerBuilder();

        $container->register(F::class);
        $gDefinition = $container->register('g', G::class);
        $gDefinition->setAutowired(true);

        (new ResolveClassPass())->process($container);
        try {
            (new AutowirePass())->process($container);
            $this->fail('AutowirePass should have thrown an exception');
        } catch (AutowiringFailedException $e) {
            $this->assertSame('Cannot autowire service "g": argument "$d" of method "Symfony\Component\DependencyInjection\Tests\Compiler\G::__construct()" references interface "Symfony\Component\DependencyInjection\Tests\Compiler\DInterface" but no such service exists. You should maybe alias this interface to the existing "Symfony\Component\DependencyInjection\Tests\Compiler\F" service.', (string) $e->getMessage());
        }
    }

    public function testCompleteExistingDefinition()
    {
        $container = new ContainerBuilder();

        $container->register('b', B::class);
        $container->register(DInterface::class, F::class);
        $hDefinition = $container->register('h', H::class)->addArgument(new Reference('b'));
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
        $hDefinition = $container->register('h', H::class)->addArgument('')->addArgument('');
        $hDefinition->setAutowired(true);

        (new ResolveClassPass())->process($container);
        (new AutowirePass())->process($container);

        $this->assertCount(2, $container->getDefinition('h')->getArguments());
        $this->assertEquals(B::class, (string) $container->getDefinition('h')->getArgument(0));
        $this->assertEquals(DInterface::class, (string) $container->getDefinition('h')->getArgument(1));
    }

    public function testPrivateConstructorThrowsAutowireException()
    {
        $container = new ContainerBuilder();

        $container->autowire('private_service', PrivateConstructor::class);

        $pass = new AutowirePass(true);
        try {
            $pass->process($container);
            $this->fail('AutowirePass should have thrown an exception');
        } catch (AutowiringFailedException $e) {
            $this->assertSame('Invalid service "private_service": constructor of class "Symfony\Component\DependencyInjection\Tests\Compiler\PrivateConstructor" must be public.', (string) $e->getMessage());
        }
    }

    public function testTypeCollision()
    {
        $container = new ContainerBuilder();

        $container->register('c1', CollisionA::class);
        $container->register('c2', CollisionB::class);
        $container->register('c3', CollisionB::class);
        $aDefinition = $container->register('a', CannotBeAutowired::class);
        $aDefinition->setAutowired(true);

        $pass = new AutowirePass();
        try {
            $pass->process($container);
            $this->fail('AutowirePass should have thrown an exception');
        } catch (AutowiringFailedException $e) {
            $this->assertSame('Cannot autowire service "a": argument "$collision" of method "Symfony\Component\DependencyInjection\Tests\Compiler\CannotBeAutowired::__construct()" references interface "Symfony\Component\DependencyInjection\Tests\Compiler\CollisionInterface" but no such service exists. You should maybe alias this interface to one of these existing services: "c1", "c2", "c3".', (string) $e->getMessage());
        }
    }

    public function testTypeNotGuessable()
    {
        $container = new ContainerBuilder();

        $container->register('a1', Foo::class);
        $container->register('a2', Foo::class);
        $aDefinition = $container->register('a', NotGuessableArgument::class);
        $aDefinition->setAutowired(true);

        $pass = new AutowirePass();
        try {
            $pass->process($container);
            $this->fail('AutowirePass should have thrown an exception');
        } catch (AutowiringFailedException $e) {
            $this->assertSame('Cannot autowire service "a": argument "$k" of method "Symfony\Component\DependencyInjection\Tests\Compiler\NotGuessableArgument::__construct()" references class "Symfony\Component\DependencyInjection\Tests\Compiler\Foo" but no such service exists. You should maybe alias this class to one of these existing services: "a1", "a2".', (string) $e->getMessage());
        }
    }

    public function testTypeNotGuessableWithSubclass()
    {
        $container = new ContainerBuilder();

        $container->register('a1', B::class);
        $container->register('a2', B::class);
        $aDefinition = $container->register('a', NotGuessableArgumentForSubclass::class);
        $aDefinition->setAutowired(true);

        $pass = new AutowirePass();
        try {
            $pass->process($container);
            $this->fail('AutowirePass should have thrown an exception');
        } catch (AutowiringFailedException $e) {
            $this->assertSame('Cannot autowire service "a": argument "$k" of method "Symfony\Component\DependencyInjection\Tests\Compiler\NotGuessableArgumentForSubclass::__construct()" references class "Symfony\Component\DependencyInjection\Tests\Compiler\A" but no such service exists. You should maybe alias this class to one of these existing services: "a1", "a2".', (string) $e->getMessage());
        }
    }

    public function testTypeNotGuessableNoServicesFound()
    {
        $container = new ContainerBuilder();

        $aDefinition = $container->register('a', CannotBeAutowired::class);
        $aDefinition->setAutowired(true);

        $pass = new AutowirePass();
        try {
            $pass->process($container);
            $this->fail('AutowirePass should have thrown an exception');
        } catch (AutowiringFailedException $e) {
            $this->assertSame('Cannot autowire service "a": argument "$collision" of method "Symfony\Component\DependencyInjection\Tests\Compiler\CannotBeAutowired::__construct()" references interface "Symfony\Component\DependencyInjection\Tests\Compiler\CollisionInterface" but no such service exists. Did you create a class that implements this interface?', (string) $e->getMessage());
        }
    }

    public function testTypeNotGuessableUnionType()
    {
        $container = new ContainerBuilder();

        $container->register(CollisionA::class);
        $container->register(CollisionB::class);

        $aDefinition = $container->register('a', UnionClasses::class);
        $aDefinition->setAutowired(true);

        $pass = new AutowirePass();

        $this->expectException(AutowiringFailedException::class);
        $this->expectExceptionMessage('Cannot autowire service "a": argument "$collision" of method "Symfony\Component\DependencyInjection\Tests\Compiler\UnionClasses::__construct()" has type "Symfony\Component\DependencyInjection\Tests\Compiler\CollisionA|Symfony\Component\DependencyInjection\Tests\Compiler\CollisionB" but this class was not found.');
        $pass->process($container);
    }

    public function testGuessableUnionType()
    {
        $container = new ContainerBuilder();

        $container->register('b', \stcClass::class);
        $container->setAlias(CollisionA::class.' $collision', 'b');
        $container->setAlias(CollisionB::class.' $collision', 'b');

        $aDefinition = $container->register('a', UnionClasses::class);
        $aDefinition->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);

        $this->assertSame('b', (string) $aDefinition->getArgument(0));
    }

    public function testTypeNotGuessableIntersectionType()
    {
        $container = new ContainerBuilder();

        $container->register(CollisionInterface::class);
        $container->register(AnotherInterface::class);

        $aDefinition = $container->register('a', IntersectionClasses::class);
        $aDefinition->setAutowired(true);

        $pass = new AutowirePass();

        $this->expectException(AutowiringFailedException::class);
        $this->expectExceptionMessage('Cannot autowire service "a": argument "$collision" of method "Symfony\Component\DependencyInjection\Tests\Compiler\IntersectionClasses::__construct()" has type "Symfony\Component\DependencyInjection\Tests\Compiler\AnotherInterface&Symfony\Component\DependencyInjection\Tests\Compiler\CollisionInterface" but this class was not found.');
        $pass->process($container);
    }

    /**
     * @requires PHP 8.2
     */
    public function testTypeNotGuessableCompositeType()
    {
        $container = new ContainerBuilder();

        $container->register(CollisionInterface::class);
        $container->register(AnotherInterface::class);

        $aDefinition = $container->register('a', CompositeTypeClasses::class);
        $aDefinition->setAutowired(true);

        $pass = new AutowirePass();

        $this->expectException(AutowiringFailedException::class);
        $this->expectExceptionMessage('Cannot autowire service "a": argument "$collision" of method "Symfony\Component\DependencyInjection\Tests\Compiler\CompositeTypeClasses::__construct()" has type "(Symfony\Component\DependencyInjection\Tests\Compiler\AnotherInterface&Symfony\Component\DependencyInjection\Tests\Compiler\CollisionInterface)|Symfony\Component\DependencyInjection\Tests\Compiler\YetAnotherInterface" but this class was not found.');
        $pass->process($container);
    }

    public function testGuessableIntersectionType()
    {
        $container = new ContainerBuilder();

        $container->register('b', \stcClass::class);
        $container->setAlias(CollisionInterface::class, 'b');
        $container->setAlias(AnotherInterface::class, 'b');
        $container->setAlias(DummyInterface::class, 'b');

        $aDefinition = $container->register('a', IntersectionClasses::class);
        $aDefinition->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);

        $this->assertSame('b', (string) $aDefinition->getArgument(0));
    }

    public function testTypeNotGuessableWithTypeSet()
    {
        $container = new ContainerBuilder();

        $container->register('a1', Foo::class);
        $container->register('a2', Foo::class);
        $container->register(Foo::class, Foo::class);
        $aDefinition = $container->register('a', NotGuessableArgument::class);
        $aDefinition->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);

        $this->assertCount(1, $container->getDefinition('a')->getArguments());
        $this->assertEquals(Foo::class, (string) $container->getDefinition('a')->getArgument(0));
    }

    public function testWithTypeSet()
    {
        $container = new ContainerBuilder();

        $container->register('c1', CollisionA::class);
        $container->register('c2', CollisionB::class);
        $container->setAlias(CollisionInterface::class, 'c2');
        $aDefinition = $container->register('a', CannotBeAutowired::class);
        $aDefinition->setAutowired(true);

        $pass = new AutowirePass();
        $pass->process($container);

        $this->assertCount(1, $container->getDefinition('a')->getArguments());
        $this->assertEquals(CollisionInterface::class, (string) $container->getDefinition('a')->getArgument(0));
    }

    public function testServicesAreNotAutoCreated()
    {
        $container = new ContainerBuilder();

        $coopTilleulsDefinition = $container->register('coop_tilleuls', LesTilleuls::class);
        $coopTilleulsDefinition->setAutowired(true);

        $pass = new AutowirePass();
        try {
            $pass->process($container);
            $this->fail('AutowirePass should have thrown an exception');
        } catch (AutowiringFailedException $e) {
            $this->assertSame('Cannot autowire service "coop_tilleuls": argument "$j" of method "Symfony\Component\DependencyInjection\Tests\Compiler\LesTilleuls::__construct()" references class "Symfony\Component\DependencyInjection\Tests\Compiler\Dunglas" but no such service exists.', (string) $e->getMessage());
        }
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
        $optDefinition = $container->register('opt', OptionalParameter::class);
        $optDefinition->setAutowired(true);

        (new ResolveClassPass())->process($container);
        (new AutowirePass())->process($container);

        $definition = $container->getDefinition('opt');
        $this->assertNull($definition->getArgument(0));
        $this->assertEquals(A::class, $definition->getArgument(1));
        $this->assertEquals(Foo::class, $definition->getArgument(2));
    }

    public function testParameterWithNullUnionIsSkipped()
    {
        $container = new ContainerBuilder();

        $optDefinition = $container->register('opt', UnionNull::class);
        $optDefinition->setAutowired(true);

        (new AutowirePass())->process($container);

        $definition = $container->getDefinition('opt');
        $this->assertNull($definition->getArgument(0));
    }

    /**
     * @requires PHP 8.2
     */
    public function testParameterWithNullableIntersectionIsSkipped()
    {
        $container = new ContainerBuilder();

        $optDefinition = $container->register('opt', NullableIntersection::class);
        $optDefinition->setAutowired(true);

        (new AutowirePass())->process($container);

        $definition = $container->getDefinition('opt');
        $this->assertNull($definition->getArgument(0));
    }

    public function testParameterWithNullUnionIsAutowired()
    {
        $container = new ContainerBuilder();

        $container->register(CollisionInterface::class, CollisionA::class);

        $optDefinition = $container->register('opt', UnionNull::class);
        $optDefinition->setAutowired(true);

        (new AutowirePass())->process($container);

        $definition = $container->getDefinition('opt');
        $this->assertEquals(CollisionInterface::class, $definition->getArgument(0));
    }

    public function testDontTriggerAutowiring()
    {
        $container = new ContainerBuilder();

        $container->register(Foo::class);
        $container->register('bar', Bar::class);

        (new ResolveClassPass())->process($container);
        (new AutowirePass())->process($container);

        $this->assertCount(0, $container->getDefinition('bar')->getArguments());
    }

    public function testClassNotFoundThrowsException()
    {
        $container = new ContainerBuilder();

        $aDefinition = $container->register('a', BadTypeHintedArgument::class);
        $aDefinition->setAutowired(true);

        $container->register(Dunglas::class, Dunglas::class);

        $pass = new AutowirePass();
        try {
            $pass->process($container);
            $this->fail('AutowirePass should have thrown an exception');
        } catch (AutowiringFailedException $e) {
            $this->assertSame('Cannot autowire service "a": argument "$r" of method "Symfony\Component\DependencyInjection\Tests\Compiler\BadTypeHintedArgument::__construct()" has type "Symfony\Component\DependencyInjection\Tests\Compiler\NotARealClass" but this class was not found.', (string) $e->getMessage());
        }
    }

    public function testParentClassNotFoundThrowsException()
    {
        $container = new ContainerBuilder();

        $aDefinition = $container->register('a', BadParentTypeHintedArgument::class);
        $aDefinition->setAutowired(true);

        $container->register(Dunglas::class, Dunglas::class);

        $pass = new AutowirePass();
        try {
            $pass->process($container);
            $this->fail('AutowirePass should have thrown an exception');
        } catch (AutowiringFailedException $e) {
            $this->assertMatchesRegularExpression('{^Cannot autowire service "a": argument "\$r" of method "(Symfony\\\\Component\\\\DependencyInjection\\\\Tests\\\\Compiler\\\\)BadParentTypeHintedArgument::__construct\(\)" has type "\1OptionalServiceClass" but this class is missing a parent class \(Class "?Symfony\\\\Bug\\\\NotExistClass"? not found}', (string) $e->getMessage());
        }
    }

    public function testParentClassNotFoundThrowsExceptionWithoutConfigComponent()
    {
        ClassExistsMock::withMockedClasses([
            ClassExistenceResource::class => false,
        ]);

        $container = new ContainerBuilder();

        $aDefinition = $container->register('a', BadParentTypeHintedArgument::class);
        $aDefinition->setAutowired(true);

        $container->register(Dunglas::class, Dunglas::class);

        $pass = new AutowirePass();
        try {
            $pass->process($container);
            $this->fail('AutowirePass should have thrown an exception');
        } catch (AutowiringFailedException $e) {
            $this->assertSame('Cannot autowire service "a": argument "$r" of method "Symfony\\Component\\DependencyInjection\\Tests\\Compiler\\BadParentTypeHintedArgument::__construct()" has type "Symfony\\Component\\DependencyInjection\\Tests\\Compiler\\OptionalServiceClass" but this class couldn\'t be loaded. Either it was not found or it is missing a parent class or a trait.', $e->getMessage());
        }

        ClassExistsMock::withMockedClasses([]);
    }

    public function testDontUseAbstractServices()
    {
        $container = new ContainerBuilder();

        $container->register(Foo::class)->setAbstract(true);
        $container->register('foo', Foo::class);
        $container->register('bar', Bar::class)->setAutowired(true);

        (new ResolveClassPass())->process($container);
        try {
            (new AutowirePass())->process($container);
            $this->fail('AutowirePass should have thrown an exception');
        } catch (AutowiringFailedException $e) {
            $this->assertSame('Cannot autowire service "bar": argument "$foo" of method "Symfony\Component\DependencyInjection\Tests\Compiler\Bar::__construct()" references class "Symfony\Component\DependencyInjection\Tests\Compiler\Foo" but this service is abstract. You should maybe alias this class to the existing "foo" service.', (string) $e->getMessage());
        }
    }

    public function testSomeSpecificArgumentsAreSet()
    {
        $container = new ContainerBuilder();

        $container->register('foo', Foo::class);
        $container->register(A::class);
        $container->register(Dunglas::class);
        $container->register('multiple', MultipleArguments::class)
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

    public function testScalarArgsCannotBeAutowired()
    {
        $container = new ContainerBuilder();

        $container->register(A::class);
        $container->register(Dunglas::class);
        $container->register('arg_no_type_hint', MultipleArguments::class)
            ->setArguments([1 => 'foo'])
            ->setAutowired(true);

        (new ResolveClassPass())->process($container);
        try {
            (new AutowirePass())->process($container);
            $this->fail('AutowirePass should have thrown an exception');
        } catch (AutowiringFailedException $e) {
            $this->assertSame('Cannot autowire service "arg_no_type_hint": argument "$bar" of method "Symfony\Component\DependencyInjection\Tests\Compiler\MultipleArguments::__construct()" is type-hinted "array", you should configure its value explicitly.', (string) $e->getMessage());
        }
    }

    public function testUnionScalarArgsCannotBeAutowired()
    {
        $this->expectException(AutowiringFailedException::class);
        $this->expectExceptionMessage('Cannot autowire service "union_scalars": argument "$timeout" of method "Symfony\Component\DependencyInjection\Tests\Compiler\UnionScalars::__construct()" is type-hinted "float|int", you should configure its value explicitly.');
        $container = new ContainerBuilder();

        $container->register('union_scalars', UnionScalars::class)
            ->setAutowired(true);

        (new AutowirePass())->process($container);
    }

    public function testNoTypeArgsCannotBeAutowired()
    {
        $container = new ContainerBuilder();

        $container->register(A::class);
        $container->register(Dunglas::class);
        $container->register('arg_no_type_hint', MultipleArguments::class)
            ->setAutowired(true);

        (new ResolveClassPass())->process($container);
        try {
            (new AutowirePass())->process($container);
            $this->fail('AutowirePass should have thrown an exception');
        } catch (AutowiringFailedException $e) {
            $this->assertSame('Cannot autowire service "arg_no_type_hint": argument "$foo" of method "Symfony\Component\DependencyInjection\Tests\Compiler\MultipleArguments::__construct()" has no type-hint, you should configure its value explicitly.', (string) $e->getMessage());
        }
    }

    public function testOptionalScalarArgsDontMessUpOrder()
    {
        $container = new ContainerBuilder();

        $container->register(A::class);
        $container->register(Lille::class);
        $container->register('with_optional_scalar', MultipleArgumentsOptionalScalar::class)
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
        $container->register('with_optional_scalar_last', MultipleArgumentsOptionalScalarLast::class)
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

    /**
     * @group legacy
     */
    public function testSetterInjectionAnnotation()
    {
        $this->expectDeprecation('Since symfony/dependency-injection 6.3: Relying on the "@required" annotation on method "Symfony\Component\DependencyInjection\Tests\Compiler\SetterInjectionAnnotation::setFoo()" is deprecated, use the "Symfony\Contracts\Service\Attribute\Required" attribute instead.');
        $this->expectDeprecation('Since symfony/dependency-injection 6.3: Relying on the "@required" annotation on method "Symfony\Component\DependencyInjection\Tests\Compiler\SetterInjectionAnnotation::setChildMethodWithoutDocBlock()" is deprecated, use the "Symfony\Contracts\Service\Attribute\Required" attribute instead.');
        $this->expectDeprecation('Since symfony/dependency-injection 6.3: Relying on the "@required" annotation on method "Symfony\Component\DependencyInjection\Tests\Compiler\SetterInjectionParentAnnotation::setDependencies()" is deprecated, use the "Symfony\Contracts\Service\Attribute\Required" attribute instead.');

        $container = new ContainerBuilder();
        $container->register(Foo::class);
        $container->register(A::class);
        $container->register(CollisionA::class);
        $container->register(CollisionB::class);

        // manually configure *one* call, to override autowiring
        $container
            ->register('setter_injection', SetterInjectionAnnotation::class)
            ->setAutowired(true)
            ->addMethodCall('setWithCallsConfigured', ['manual_arg1', 'manual_arg2'])
        ;

        (new ResolveClassPass())->process($container);
        (new AutowireRequiredMethodsPass())->process($container);
        (new AutowirePass())->process($container);

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
        $this->assertEquals(
            [new TypedReference(Foo::class, Foo::class)],
            $methodCalls[1][1]
        );
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
        (new AutowirePass())->process($container);

        $methodCalls = $container->getDefinition('setter_injection')->getMethodCalls();
        $this->assertCount(1, $methodCalls);
        $this->assertSame('setFoo', $methodCalls[0][0]);
        $this->assertSame(Foo::class, (string) $methodCalls[0][1][0]);
    }

    public function testWithNonExistingSetterAndAutowiring()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid service "Symfony\Component\DependencyInjection\Tests\Fixtures\CaseSensitiveClass": method "setLogger()" does not exist.');
        $container = new ContainerBuilder();

        $definition = $container->register(CaseSensitiveClass::class, CaseSensitiveClass::class)->setAutowired(true);
        $definition->addMethodCall('setLogger');

        (new ResolveClassPass())->process($container);
        (new AutowireRequiredMethodsPass())->process($container);
        (new AutowirePass())->process($container);
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

        $container->register('class_not_exist', OptionalServiceClass::class);

        $barDefinition = $container->register('bar', Bar::class);
        $barDefinition->setAutowired(true);

        $container->register(Foo::class, Foo::class);

        $pass = new AutowirePass();
        $pass->process($container);

        $this->assertTrue($container->hasDefinition('bar'));
    }

    /**
     * @group legacy
     */
    public function testSetterInjectionFromAnnotationCollisionThrowsException()
    {
        $this->expectDeprecation('Since symfony/dependency-injection 6.3: Relying on the "@required" annotation on method "Symfony\Component\DependencyInjection\Tests\Compiler\SetterInjectionCollisionAnnotation::setMultipleInstancesForOneArg()" is deprecated, use the "Symfony\Contracts\Service\Attribute\Required" attribute instead.');

        $container = new ContainerBuilder();

        $container->register('c1', CollisionA::class);
        $container->register('c2', CollisionB::class);
        $aDefinition = $container->register('setter_injection_collision', SetterInjectionCollisionAnnotation::class);
        $aDefinition->setAutowired(true);

        (new AutowireRequiredMethodsPass())->process($container);

        $pass = new AutowirePass();

        try {
            $pass->process($container);
            $this->fail('AutowirePass should have thrown an exception');
        } catch (AutowiringFailedException $e) {
            $this->assertSame('Cannot autowire service "setter_injection_collision": argument "$collision" of method "Symfony\Component\DependencyInjection\Tests\Compiler\SetterInjectionCollisionAnnotation::setMultipleInstancesForOneArg()" references interface "Symfony\Component\DependencyInjection\Tests\Compiler\CollisionInterface" but no such service exists. You should maybe alias this interface to one of these existing services: "c1", "c2".', (string) $e->getMessage());
        }
    }

    public function testSetterInjectionFromAttributeCollisionThrowsException()
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
            $this->fail('AutowirePass should have thrown an exception');
        } catch (AutowiringFailedException $e) {
            $this->assertSame('Cannot autowire service "setter_injection_collision": argument "$collision" of method "Symfony\Component\DependencyInjection\Tests\Compiler\SetterInjectionCollision::setMultipleInstancesForOneArg()" references interface "Symfony\Component\DependencyInjection\Tests\Compiler\CollisionInterface" but no such service exists. You should maybe alias this interface to one of these existing services: "c1", "c2".', (string) $e->getMessage());
        }
    }

    public function testInterfaceWithNoImplementationSuggestToWriteOne()
    {
        $container = new ContainerBuilder();

        $aDefinition = $container->register('my_service', K::class);
        $aDefinition->setAutowired(true);

        (new AutowireRequiredMethodsPass())->process($container);

        $pass = new AutowirePass();
        try {
            $pass->process($container);
            $this->fail('AutowirePass should have thrown an exception');
        } catch (AutowiringFailedException $e) {
            $this->assertSame('Cannot autowire service "my_service": argument "$i" of method "Symfony\Component\DependencyInjection\Tests\Compiler\K::__construct()" references interface "Symfony\Component\DependencyInjection\Tests\Compiler\IInterface" but no such service exists. Did you create a class that implements this interface?', (string) $e->getMessage());
        }
    }

    public function testProcessDoesNotTriggerDeprecations()
    {
        $container = new ContainerBuilder();
        $container->register('deprecated', 'Symfony\Component\DependencyInjection\Tests\Fixtures\DeprecatedClass')->setDeprecated('vendor/package', '1.1', '%service_id%');
        $container->register('foo', Foo::class);
        $container->register('bar', Bar::class)->setAutowired(true);

        $pass = new AutowirePass();
        try {
            $pass->process($container);
            $this->fail('AutowirePass should have thrown an exception');
        } catch (AutowiringFailedException $e) {
            $this->assertSame('Cannot autowire service "bar": argument "$foo" of method "Symfony\Component\DependencyInjection\Tests\Compiler\Bar::__construct()" references class "Symfony\Component\DependencyInjection\Tests\Compiler\Foo" but no such service exists. You should maybe alias this class to the existing "foo" service.', (string) $e->getMessage());
        }

        $this->assertTrue($container->hasDefinition('deprecated'));
        $this->assertTrue($container->hasDefinition('foo'));
        $this->assertTrue($container->hasDefinition('bar'));
    }

    public function testEmptyStringIsKept()
    {
        $container = new ContainerBuilder();

        $container->register(A::class);
        $container->register(Lille::class);
        $container->register('foo', MultipleArgumentsOptionalScalar::class)
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

        (new ResolveClassPass())->process($container);
        (new AutowireRequiredMethodsPass())->process($container);
        try {
            (new AutowirePass())->process($container);
            $this->fail('AutowirePass should throw a RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertSame($expectedMsg, (string) $e->getMessage());
        }
    }

    public static function provideNotWireableCalls()
    {
        return [
            ['setNotAutowireable', 'Cannot autowire service "foo": argument "$n" of method "Symfony\Component\DependencyInjection\Tests\Compiler\NotWireable::setNotAutowireable()" has type "Symfony\Component\DependencyInjection\Tests\Compiler\NotARealClass" but this class was not found.'],
            ['setDifferentNamespace', 'Cannot autowire service "foo": argument "$n" of method "Symfony\Component\DependencyInjection\Tests\Compiler\NotWireable::setDifferentNamespace()" references class "stdClass" but no such service exists.'],
            [null, 'Invalid service "foo": method "Symfony\Component\DependencyInjection\Tests\Compiler\NotWireable::setProtectedMethod()" must be public.'],
        ];
    }

    public function testSuggestRegisteredServicesWithSimilarCase()
    {
        $container = new ContainerBuilder();

        $container->register(LesTilleuls::class, LesTilleuls::class);
        $container->register('foo', NotWireable::class)->setAutowired(true)
            ->addMethodCall('setNotAutowireableBecauseOfATypo', [])
        ;

        (new ResolveClassPass())->process($container);
        (new AutowireRequiredMethodsPass())->process($container);
        try {
            (new AutowirePass())->process($container);
            $this->fail('AutowirePass should have thrown an exception');
        } catch (AutowiringFailedException $e) {
            $this->assertSame('Cannot autowire service "foo": argument "$sam" of method "Symfony\Component\DependencyInjection\Tests\Compiler\NotWireable::setNotAutowireableBecauseOfATypo()" references class "Symfony\Component\DependencyInjection\Tests\Compiler\lesTilleuls" but no such service exists. Did you mean "Symfony\Component\DependencyInjection\Tests\Compiler\LesTilleuls"?', (string) $e->getMessage());
        }
    }

    public function testByIdAlternative()
    {
        $container = new ContainerBuilder();

        $container->setAlias(IInterface::class, 'i');
        $container->register('i', I::class);
        $container->register('j', J::class)
            ->setAutowired(true);

        $pass = new AutowirePass();
        try {
            $pass->process($container);
            $this->fail('AutowirePass should have thrown an exception');
        } catch (AutowiringFailedException $e) {
            $this->assertSame('Cannot autowire service "j": argument "$i" of method "Symfony\Component\DependencyInjection\Tests\Compiler\J::__construct()" references class "Symfony\Component\DependencyInjection\Tests\Compiler\I" but no such service exists. Try changing the type-hint to "Symfony\Component\DependencyInjection\Tests\Compiler\IInterface" instead.', (string) $e->getMessage());
        }
    }

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
        try {
            $pass->process($container);
            $this->fail('AutowirePass should have thrown an exception');
        } catch (AutowiringFailedException $e) {
            $this->assertSame('Cannot autowire service "j": argument "$i" of method "Symfony\Component\DependencyInjection\Tests\Compiler\J::__construct()" references class "Symfony\Component\DependencyInjection\Tests\Compiler\I" but no such service exists. Try changing the type-hint to "Symfony\Component\DependencyInjection\Tests\Compiler\IInterface" instead.', (string) $e->getMessage());
        }
    }

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
        try {
            $pass->process($container);
            $this->fail('AutowirePass should have thrown an exception');
        } catch (AutowiringFailedException $e) {
            $this->assertSame('Cannot autowire service "j": argument "$i" of method "Symfony\Component\DependencyInjection\Tests\Compiler\J::__construct()" references class "Symfony\Component\DependencyInjection\Tests\Compiler\I" but no such service exists. You should maybe alias this class to one of these existing services: "i", "i2".', (string) $e->getMessage());
        }
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
        try {
            (new AutowirePass())->process($container);
            $this->fail('AutowirePass should have thrown an exception');
        } catch (AutowiringFailedException $e) {
            $this->assertSame('Cannot autowire service "Symfony\Component\DependencyInjection\Tests\Compiler\NonAutowirableDecorator": argument "$decorated1" of method "__construct()" references interface "Symfony\Component\DependencyInjection\Tests\Compiler\DecoratorInterface" but no such service exists. You should maybe alias this interface to one of these existing services: "Symfony\Component\DependencyInjection\Tests\Compiler\NonAutowirableDecorator", "Symfony\Component\DependencyInjection\Tests\Compiler\NonAutowirableDecorator.inner".', (string) $e->getMessage());
        }
    }

    public function testErroredServiceLocator()
    {
        $container = new ContainerBuilder();
        $container->register('some_locator', 'stdClass')
            ->addArgument(new TypedReference(MissingClass::class, MissingClass::class, ContainerBuilder::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE))
            ->addTag('container.service_locator');

        (new AutowirePass())->process($container);

        $this->assertSame(['Cannot autowire service "some_locator": it has type "Symfony\Component\DependencyInjection\Tests\Compiler\MissingClass" but this class was not found.'], $container->getDefinition('.errored.some_locator.'.MissingClass::class)->getErrors());
    }

    /**
     * @group legacy
     */
    public function testNamedArgumentAliasResolveCollisionsAnnotation()
    {
        $this->expectDeprecation('Since symfony/dependency-injection 6.3: Relying on the "@required" annotation on method "Symfony\Component\DependencyInjection\Tests\Compiler\SetterInjectionCollisionAnnotation::setMultipleInstancesForOneArg()" is deprecated, use the "Symfony\Contracts\Service\Attribute\Required" attribute instead.');

        $container = new ContainerBuilder();

        $container->register('c1', CollisionA::class);
        $container->register('c2', CollisionB::class);
        $container->setAlias(CollisionInterface::class.' $collision', 'c2');
        $aDefinition = $container->register('setter_injection_collision', SetterInjectionCollisionAnnotation::class);
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

    public function testArgumentWithTarget()
    {
        $container = new ContainerBuilder();

        $container->register(BarInterface::class, BarInterface::class);
        $container->register(BarInterface::class.' $imageStorage', BarInterface::class);
        $container->register('with_target', WithTarget::class)
            ->setAutowired(true);

        (new AutowirePass())->process($container);

        $this->assertSame(BarInterface::class.' $imageStorage', (string) $container->getDefinition('with_target')->getArgument(0));
    }

    public function testArgumentWithTypoTarget()
    {
        $container = new ContainerBuilder();

        $container->register(BarInterface::class, BarInterface::class);
        $container->register(BarInterface::class.' $iamgeStorage', BarInterface::class);
        $container->register('with_target', WithTarget::class)
            ->setAutowired(true);

        $this->expectException(AutowiringFailedException::class);
        $this->expectExceptionMessage('Cannot autowire service "with_target": "#[Target(\'imageStorage\')" on argument "$bar" of method "Symfony\Component\DependencyInjection\Tests\Fixtures\WithTarget::__construct()"');

        (new AutowirePass())->process($container);
    }

    public function testDecorationWithServiceAndAliasedInterface()
    {
        $container = new ContainerBuilder();

        $container->register(DecoratorImpl::class, DecoratorImpl::class)
            ->setAutowired(true)
            ->setPublic(true);
        $container->setAlias(DecoratorInterface::class, DecoratorImpl::class)->setPublic(true);
        $container->register(DecoratedDecorator::class, DecoratedDecorator::class)
            ->setAutowired(true)
            ->setPublic(true)
            ->setDecoratedService(DecoratorImpl::class);

        $container->compile();

        static::assertInstanceOf(DecoratedDecorator::class, $container->get(DecoratorInterface::class));
        static::assertInstanceOf(DecoratedDecorator::class, $container->get(DecoratorImpl::class));
    }

    public function testAutowireWithNamedArgs()
    {
        $container = new ContainerBuilder();

        $container->register('foo', MultipleArgumentsOptionalScalar::class)
            ->setArguments(['foo' => 'abc'])
            ->setAutowired(true)
            ->setPublic(true);
        $container->register(A::class, A::class);

        (new AutowirePass())->process($container);

        $this->assertEquals([new TypedReference(A::class, A::class), 'abc'], $container->getDefinition('foo')->getArguments());
    }

    public function testAutowireAttribute()
    {
        $container = new ContainerBuilder();

        $container->register(AutowireAttribute::class)
            ->setAutowired(true)
            ->setPublic(true)
        ;

        $container->register('some.id', \stdClass::class);
        $container->setParameter('some.parameter', 'foo');
        $container->setParameter('null.parameter', null);

        (new ResolveClassPass())->process($container);
        (new AutowirePass())->process($container);

        $definition = $container->getDefinition(AutowireAttribute::class);

        $this->assertCount(10, $definition->getArguments());
        $this->assertEquals(new TypedReference('some.id', 'stdClass', attributes: [new Autowire(service: 'some.id')]), $definition->getArgument(0));
        $this->assertEquals(new Expression("parameter('some.parameter')"), $definition->getArgument(1));
        $this->assertSame('foo/bar', $definition->getArgument(2));
        $this->assertNull($definition->getArgument(3));
        $this->assertEquals(new TypedReference('some.id', 'stdClass', attributes: [new Autowire(service: 'some.id')]), $definition->getArgument(4));
        $this->assertEquals(new Expression("parameter('some.parameter')"), $definition->getArgument(5));
        $this->assertSame('bar', $definition->getArgument(6));
        $this->assertSame('@bar', $definition->getArgument(7));
        $this->assertSame('foo', $definition->getArgument(8));
        $this->assertEquals(new TypedReference('invalid.id', 'stdClass', ContainerInterface::NULL_ON_INVALID_REFERENCE, attributes: [new Autowire(service: 'invalid.id')]), $definition->getArgument(9));

        $container->compile();

        $service = $container->get(AutowireAttribute::class);

        $this->assertInstanceOf(\stdClass::class, $service->service);
        $this->assertSame('foo', $service->expression);
        $this->assertSame('foo/bar', $service->value);
        $this->assertInstanceOf(\stdClass::class, $service->serviceAsValue);
        $this->assertSame('foo', $service->expressionAsValue);
        $this->assertSame('bar', $service->rawValue);
        $this->assertSame('@bar', $service->escapedRawValue);
        $this->assertSame('foo', $service->customAutowire);
        $this->assertNull($service->invalid);
    }

    public function testAutowireAttributeNullFallbackTestRequired()
    {
        $container = new ContainerBuilder();

        $container->register('foo', AutowireAttributeNullFallback::class)
            ->setAutowired(true)
            ->setPublic(true)
        ;

        $this->expectException(AutowiringFailedException::class);
        $this->expectExceptionMessage('You have requested a non-existent parameter "required.parameter".');
        (new AutowirePass())->process($container);
    }

    public function testAutowireAttributeNullFallbackTestOptional()
    {
        $container = new ContainerBuilder();

        $container->register('foo', AutowireAttributeNullFallback::class)
            ->setAutowired(true)
            ->setPublic(true)
        ;

        $container->setParameter('required.parameter', 'foo');

        (new AutowirePass())->process($container);

        $definition = $container->getDefinition('foo');

        $this->assertSame(['foo'], $definition->getArguments());
    }

    public function testAsDecoratorAttribute()
    {
        $container = new ContainerBuilder();

        $container->register(AsDecoratorFoo::class);
        $container->register(AsDecoratorBar10::class)->setAutowired(true)->setArgument(0, 'arg1');
        $container->register(AsDecoratorBar20::class)->setAutowired(true)->setArgument(0, 'arg1');
        $container->register(AsDecoratorBaz::class)->setAutowired(true);

        (new ResolveClassPass())->process($container);
        (new AutowireAsDecoratorPass())->process($container);
        (new DecoratorServicePass())->process($container);
        (new AutowirePass())->process($container);

        $this->assertSame(AsDecoratorBar10::class.'.inner', (string) $container->getDefinition(AsDecoratorBar10::class)->getArgument(1));

        $this->assertSame(AsDecoratorBar20::class.'.inner', (string) $container->getDefinition(AsDecoratorBar20::class)->getArgument(1));
        $this->assertSame(AsDecoratorBaz::class.'.inner', (string) $container->getDefinition(AsDecoratorBaz::class)->getArgument(0));
        $this->assertSame(2, $container->getDefinition(AsDecoratorBaz::class)->getArgument(0)->getInvalidBehavior());
    }

    public function testTypeSymbolExcluded()
    {
        $container = new ContainerBuilder();

        $container->register(Foo::class)->setAbstract(true)->addTag('container.excluded', ['source' => 'for tests']);
        $aDefinition = $container->register('a', NotGuessableArgument::class);
        $aDefinition->setAutowired(true);

        $pass = new AutowirePass();
        try {
            $pass->process($container);
            $this->fail('AutowirePass should have thrown an exception');
        } catch (AutowiringFailedException $e) {
            $this->assertSame('Cannot autowire service "a": argument "$k" of method "Symfony\Component\DependencyInjection\Tests\Compiler\NotGuessableArgument::__construct()" needs an instance of "Symfony\Component\DependencyInjection\Tests\Compiler\Foo" but this type has been excluded for tests.', (string) $e->getMessage());
        }
    }

    public function testTypeNamespaceExcluded()
    {
        $container = new ContainerBuilder();

        $container->register(__NAMESPACE__)->setAbstract(true)->addTag('container.excluded');
        $aDefinition = $container->register('a', NotGuessableArgument::class);
        $aDefinition->setAutowired(true);

        $pass = new AutowirePass();
        try {
            $pass->process($container);
            $this->fail('AutowirePass should have thrown an exception');
        } catch (AutowiringFailedException $e) {
            $this->assertSame('Cannot autowire service "a": argument "$k" of method "Symfony\Component\DependencyInjection\Tests\Compiler\NotGuessableArgument::__construct()" needs an instance of "Symfony\Component\DependencyInjection\Tests\Compiler\Foo" but this type has been excluded from autowiring.', (string) $e->getMessage());
        }
    }

    public function testNestedAttributes()
    {
        $container = new ContainerBuilder();

        $container->register(AsDecoratorFoo::class);
        $container->register(AutowireNestedAttributes::class)->setAutowired(true);

        (new ResolveClassPass())->process($container);
        (new AutowireAsDecoratorPass())->process($container);
        (new DecoratorServicePass())->process($container);
        (new AutowirePass())->process($container);

        $expected = [
            'decorated' => new Reference(AutowireNestedAttributes::class.'.inner'),
            'iterator' => new TaggedIteratorArgument('foo'),
            'locator' => new ServiceLocatorArgument(new TaggedIteratorArgument('foo', needsIndexes: true)),
            'service' => new Reference('bar'),
        ];
        $this->assertEquals($expected, $container->getDefinition(AutowireNestedAttributes::class)->getArgument(0));
    }
}
