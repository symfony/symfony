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
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Compiler\CheckTypeDeclarationsPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveParameterPlaceHoldersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\InvalidParameterTypeException;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass\Bar;
use Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass\BarMethodCall;
use Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass\BarOptionalArgument;
use Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass\BarOptionalArgumentNotNull;
use Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass\Deprecated;
use Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass\Foo;
use Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass\FooObject;
use Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass\IntersectionConstructor;
use Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass\UnionConstructor;
use Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass\Waldo;
use Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass\WaldoFoo;
use Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass\Wobble;
use Symfony\Component\ExpressionLanguage\Expression;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Julien Maulny <jmaulny@darkmira.fr>
 */
class CheckTypeDeclarationsPassTest extends TestCase
{
    public function testProcessThrowsExceptionOnInvalidTypesConstructorArguments()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid definition for service "bar": argument 1 of "Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CheckTypeDeclarationsPass\\Bar::__construct()" accepts "stdClass", "Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CheckTypeDeclarationsPass\\Foo" passed.');

        $container = new ContainerBuilder();

        $container->register('foo', Foo::class);
        $container->register('bar', Bar::class)
            ->addArgument(new Reference('foo'));

        (new CheckTypeDeclarationsPass(true))->process($container);
    }

    public function testProcessThrowsExceptionOnInvalidTypesMethodCallArguments()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid definition for service "bar": argument 1 of "Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CheckTypeDeclarationsPass\\BarMethodCall::setFoo()" accepts "stdClass", "Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CheckTypeDeclarationsPass\\Foo" passed.');

        $container = new ContainerBuilder();

        $container->register('foo', Foo::class);
        $container->register('bar', BarMethodCall::class)
            ->addMethodCall('setFoo', [new Reference('foo')]);

        (new CheckTypeDeclarationsPass(true))->process($container);
    }

    public function testProcessFailsWhenPassingNullToRequiredArgument()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid definition for service "bar": argument 1 of "Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CheckTypeDeclarationsPass\\Bar::__construct()" accepts "stdClass", "null" passed.');

        $container = new ContainerBuilder();

        $container->register('bar', Bar::class)
            ->addArgument(null);

        (new CheckTypeDeclarationsPass(true))->process($container);
    }

    public function testProcessThrowsExceptionWhenMissingArgumentsInConstructor()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid definition for service "bar": "Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CheckTypeDeclarationsPass\\Bar::__construct()" requires 1 arguments, 0 passed.');

        $container = new ContainerBuilder();

        $container->register('bar', Bar::class);

        (new CheckTypeDeclarationsPass(true))->process($container);
    }

    public function testProcessSuccessWhenPassingTooManyArgumentInConstructor()
    {
        $container = new ContainerBuilder();

        $container->register('foo', \stdClass::class);
        $container->register('bar', Bar::class)
            ->addArgument(new Reference('foo'))
            ->addArgument(new Reference('foo'));

        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->addToAssertionCount(1);
    }

    public function testProcessRegisterWithClassName()
    {
        $container = new ContainerBuilder();

        $container->register(Foo::class, Foo::class);

        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->assertInstanceOf(Foo::class, $container->get(Foo::class));
    }

    public function testProcessThrowsExceptionWhenMissingArgumentsInMethodCall()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid definition for service "bar": "Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CheckTypeDeclarationsPass\\BarMethodCall::setFoo()" requires 1 arguments, 0 passed.');

        $container = new ContainerBuilder();

        $container->register('foo', \stdClass::class);
        $container->register('bar', BarMethodCall::class)
            ->addArgument(new Reference('foo'))
            ->addMethodCall('setFoo', []);

        (new CheckTypeDeclarationsPass(true))->process($container);
    }

    public function testProcessVariadicFails()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid definition for service "bar": argument 2 of "Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CheckTypeDeclarationsPass\\BarMethodCall::setFoosVariadic()" accepts "Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CheckTypeDeclarationsPass\\Foo", "stdClass" passed.');

        $container = new ContainerBuilder();

        $container->register('stdClass', \stdClass::class);
        $container->register('foo', Foo::class);
        $container->register('bar', BarMethodCall::class)
            ->addMethodCall('setFoosVariadic', [
                new Reference('foo'),
                new Reference('foo'),
                new Reference('stdClass'),
            ]);

        (new CheckTypeDeclarationsPass(true))->process($container);
    }

    public function testProcessVariadicFailsOnPassingBadTypeOnAnotherArgument()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid definition for service "bar": argument 1 of "Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CheckTypeDeclarationsPass\\BarMethodCall::setFoosVariadic()" accepts "Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CheckTypeDeclarationsPass\\Foo", "stdClass" passed.');

        $container = new ContainerBuilder();

        $container->register('stdClass', \stdClass::class);
        $container->register('bar', BarMethodCall::class)
            ->addMethodCall('setFoosVariadic', [
                new Reference('stdClass'),
            ]);

        (new CheckTypeDeclarationsPass(true))->process($container);
    }

    public function testProcessVariadicSuccess()
    {
        $container = new ContainerBuilder();

        $container->register('foo', Foo::class);
        $container->register('bar', BarMethodCall::class)
            ->addMethodCall('setFoosVariadic', [
                new Reference('foo'),
                new Reference('foo'),
                new Reference('foo'),
            ]);

        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->assertInstanceOf(Foo::class, $container->get('bar')->foo);
    }

    public function testProcessSuccessWhenNotUsingOptionalArgument()
    {
        $container = new ContainerBuilder();

        $container->register('foo', Foo::class);
        $container->register('bar', BarMethodCall::class)
            ->addMethodCall('setFoosOptional', [
                new Reference('foo'),
            ]);

        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->assertInstanceOf(Foo::class, $container->get('bar')->foo);
    }

    public function testProcessSuccessWhenUsingOptionalArgumentWithGoodType()
    {
        $container = new ContainerBuilder();

        $container->register('foo', Foo::class);
        $container->register('bar', BarMethodCall::class)
            ->addMethodCall('setFoosOptional', [
                new Reference('foo'),
                new Reference('foo'),
            ]);

        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->assertInstanceOf(Foo::class, $container->get('bar')->foo);
    }

    public function testProcessFailsWhenUsingOptionalArgumentWithBadType()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid definition for service "bar": argument 2 of "Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CheckTypeDeclarationsPass\\BarMethodCall::setFoosOptional()" accepts "Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CheckTypeDeclarationsPass\\Foo", "stdClass" passed.');

        $container = new ContainerBuilder();

        $container->register('stdClass', \stdClass::class);
        $container->register('foo', Foo::class);
        $container->register('bar', BarMethodCall::class)
            ->addMethodCall('setFoosOptional', [
                new Reference('foo'),
                new Reference('stdClass'),
            ]);

        (new CheckTypeDeclarationsPass(true))->process($container);
    }

    public function testProcessSuccessWhenPassingNullToOptional()
    {
        $container = new ContainerBuilder();

        $container->register('bar', BarOptionalArgument::class)
            ->addArgument(null);

        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->assertNull($container->get('bar')->foo);
    }

    public function testProcessSuccessWhenPassingNullToOptionalThatDoesNotAcceptNull()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid definition for service "bar": argument 1 of "Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CheckTypeDeclarationsPass\\BarOptionalArgumentNotNull::__construct()" accepts "int", "null" passed.');

        $container = new ContainerBuilder();

        $container->register('bar', BarOptionalArgumentNotNull::class)
            ->addArgument(null);

        (new CheckTypeDeclarationsPass(true))->process($container);
    }

    public function testProcessFailsWhenPassingBadTypeToOptional()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid definition for service "bar": argument 1 of "Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CheckTypeDeclarationsPass\\BarOptionalArgument::__construct()" accepts "stdClass", "string" passed.');

        $container = new ContainerBuilder();

        $container->register('bar', BarOptionalArgument::class)
            ->addArgument('string instead of stdClass');

        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->assertNull($container->get('bar')->foo);
    }

    public function testProcessSuccessScalarType()
    {
        $container = new ContainerBuilder();

        $container->register('bar', BarMethodCall::class)
            ->addMethodCall('setScalars', [
                1,
                'string',
            ]);

        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->assertInstanceOf(BarMethodCall::class, $container->get('bar'));
    }

    public function testProcessFailsOnPassingScalarTypeToConstructorTypedWithClass()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid definition for service "bar": argument 1 of "Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CheckTypeDeclarationsPass\\Bar::__construct()" accepts "stdClass", "int" passed.');

        $container = new ContainerBuilder();

        $container->register('bar', Bar::class)
            ->addArgument(1);

        (new CheckTypeDeclarationsPass(true))->process($container);
    }

    public function testProcessFailsOnPassingScalarTypeToMethodTypedWithClass()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid definition for service "bar": argument 1 of "Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CheckTypeDeclarationsPass\\BarMethodCall::setFoo()" accepts "stdClass", "string" passed.');

        $container = new ContainerBuilder();

        $container->register('bar', BarMethodCall::class)
            ->addMethodCall('setFoo', [
                'builtin type instead of class',
            ]);

        (new CheckTypeDeclarationsPass(true))->process($container);
    }

    public function testProcessFailsOnPassingClassToScalarTypedParameter()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid definition for service "bar": argument 1 of "Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CheckTypeDeclarationsPass\\BarMethodCall::setScalars()" accepts "int", "Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CheckTypeDeclarationsPass\\Foo" passed.');

        $container = new ContainerBuilder();

        $container->register('foo', Foo::class);
        $container->register('bar', BarMethodCall::class)
            ->addMethodCall('setScalars', [
                new Reference('foo'),
                new Reference('foo'),
            ]);

        (new CheckTypeDeclarationsPass(true))->process($container);
    }

    public function testProcessSuccessOnPassingBadScalarType()
    {
        $container = new ContainerBuilder();

        $container->register('bar', BarMethodCall::class)
            ->addMethodCall('setScalars', [
                1,
                true,
            ]);

        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->assertInstanceOf(BarMethodCall::class, $container->get('bar'));
    }

    public function testProcessSuccessPassingBadScalarTypeOptionalArgument()
    {
        $container = new ContainerBuilder();

        $container->register('bar', BarMethodCall::class)
            ->addMethodCall('setScalars', [
                1,
                'string',
                'string instead of optional boolean',
            ]);

        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->assertInstanceOf(BarMethodCall::class, $container->get('bar'));
    }

    public function testProcessSuccessWhenPassingArray()
    {
        $container = new ContainerBuilder();

        $container->register('bar', BarMethodCall::class)
            ->addMethodCall('setArray', [[]]);

        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->assertInstanceOf(BarMethodCall::class, $container->get('bar'));
    }

    public function testProcessSuccessWhenPassingIntegerToArrayTypedParameter()
    {
        $this->expectException(InvalidParameterTypeException::class);
        $this->expectExceptionMessage('Invalid definition for service "bar": argument 1 of "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass\BarMethodCall::setArray()" accepts "array", "int" passed.');

        $container = new ContainerBuilder();

        $container->register('bar', BarMethodCall::class)
            ->addMethodCall('setArray', [1]);

        (new CheckTypeDeclarationsPass(true))->process($container);
    }

    public function testProcessSuccessWhenPassingAnIteratorArgumentToIterable()
    {
        $container = new ContainerBuilder();

        $container->register('bar', BarMethodCall::class)
            ->addMethodCall('setIterable', [new IteratorArgument([])]);

        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->addToAssertionCount(1);
    }

    public function testProcessSuccessWhenPassingDefinitionForObjectType()
    {
        $container = new ContainerBuilder();

        $container->register('foo_object', FooObject::class)
            ->addArgument(new Definition(Foo::class));

        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->addToAssertionCount(1);
    }

    public function testProcessFactory()
    {
        $container = new ContainerBuilder();

        $container->register('foo', Foo::class);
        $container->register('bar', Bar::class)
            ->setFactory([
                new Reference('foo'),
                'createBar',
            ]);

        /* Asserts that the class of Bar is well detected */
        $container->register('bar_call', BarMethodCall::class)
            ->addMethodCall('setBar', [new Reference('bar')]);

        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->assertInstanceOf(Bar::class, $container->get('bar'));
    }

    public function testProcessFactoryFailsOnInvalidParameterType()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid definition for service "bar": argument 1 of "Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CheckTypeDeclarationsPass\\Foo::createBarArguments()" accepts "stdClass", "Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CheckTypeDeclarationsPass\\Foo" passed.');

        $container = new ContainerBuilder();

        $container->register('foo', Foo::class);
        $container->register('bar', Bar::class)
            ->addArgument(new Reference('foo'))
            ->setFactory([
                new Reference('foo'),
                'createBarArguments',
            ]);

        (new CheckTypeDeclarationsPass(true))->process($container);
    }

    public function testProcessFactoryFailsOnInvalidParameterTypeOptional()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid definition for service "bar": argument 2 of "Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CheckTypeDeclarationsPass\\Foo::createBarArguments()" accepts "stdClass", "Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CheckTypeDeclarationsPass\\Foo" passed.');

        $container = new ContainerBuilder();

        $container->register('stdClass', \stdClass::class);
        $container->register('foo', Foo::class);
        $container->register('bar', Bar::class)
            ->addArgument(new Reference('stdClass'))
            ->addArgument(new Reference('foo'))
            ->setFactory([
                new Reference('foo'),
                'createBarArguments',
            ]);

        (new CheckTypeDeclarationsPass(true))->process($container);
    }

    public function testProcessFactorySuccessOnValidTypes()
    {
        $container = new ContainerBuilder();

        $container->register('stdClass', \stdClass::class);
        $container->register('foo', Foo::class);
        $container->register('bar', Bar::class)
            ->addArgument(new Reference('stdClass'))
            ->addArgument(new Reference('stdClass'))
            ->setFactory([
                new Reference('foo'),
                'createBarArguments',
            ]);

        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->addToAssertionCount(1);
    }

    public function testProcessFactoryCallbackSuccessOnValidType()
    {
        $container = new ContainerBuilder();

        $container->register('bar', \DateTimeImmutable::class)
            ->setFactory('date_create_immutable');

        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->assertInstanceOf(\DateTimeImmutable::class, $container->get('bar'));
    }

    public function testProcessDoesNotLoadCodeByDefault()
    {
        $container = new ContainerBuilder();

        $container->register('foo', FooNotExisting::class);
        $container->register('bar', BarNotExisting::class)
            ->addArgument(new Reference('foo'))
            ->addMethodCall('setFoo', [
                new Reference('foo'),
                'string',
                1,
            ]);

        (new CheckTypeDeclarationsPass())->process($container);

        $this->addToAssertionCount(1);
    }

    public function testProcessFactoryDoesNotLoadCodeByDefault()
    {
        $container = new ContainerBuilder();

        $container->register('foo', FooNotExisting::class);
        $container->register('bar', BarNotExisting::class)
            ->setFactory([
                new Reference('foo'),
                'notExistingMethod',
            ]);

        (new CheckTypeDeclarationsPass())->process($container);

        $this->addToAssertionCount(1);
    }

    public function testProcessFactoryForTypeSameAsClass()
    {
        $container = new ContainerBuilder();

        $container->register('foo', Foo::class);
        $container->register('bar', 'callable')
            ->setFactory([
                new Reference('foo'),
                'createCallable',
            ]);
        $container->register('bar_call', BarMethodCall::class)
            ->addMethodCall('setCallable', [new Reference('bar')]);

        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->addToAssertionCount(1);
    }

    public function testProcessFactoryForIterableTypeAndArrayClass()
    {
        $container = new ContainerBuilder();

        $container->register('foo', Foo::class);
        $container->register('bar', 'array')
            ->setFactory([
                new Reference('foo'),
                'createArray',
            ]);
        $container->register('bar_call', BarMethodCall::class)
            ->addMethodCall('setIterable', [new Reference('bar')]);

        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->addToAssertionCount(1);
    }

    public function testProcessPassingBuiltinTypeDoesNotLoadCodeByDefault()
    {
        $container = new ContainerBuilder();

        $container->register('bar', BarNotExisting::class)
            ->addArgument(1);

        (new CheckTypeDeclarationsPass())->process($container);

        $this->addToAssertionCount(1);
    }

    public function testProcessDoesNotThrowsExceptionOnValidTypes()
    {
        $container = new ContainerBuilder();

        $container->register('foo', \stdClass::class);
        $container->register('bar', Bar::class)
            ->addArgument(new Reference('foo'));

        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->assertInstanceOf(\stdClass::class, $container->get('bar')->foo);
    }

    public function testProcessThrowsOnIterableTypeWhenScalarPassed()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid definition for service "bar_call": argument 1 of "Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CheckTypeDeclarationsPass\\BarMethodCall::setIterable()" accepts "iterable", "int" passed.');

        $container = new ContainerBuilder();

        $container->register('bar_call', BarMethodCall::class)
            ->addMethodCall('setIterable', [2]);

        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->assertInstanceOf(\stdClass::class, $container->get('bar')->foo);
    }

    public function testProcessResolveExpressions()
    {
        $container = new ContainerBuilder();
        $container->setParameter('ccc', ['array']);

        $container
            ->register('foobar', BarMethodCall::class)
            ->addMethodCall('setArray', [new Expression("parameter('ccc')")]);

        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->addToAssertionCount(1);
    }

    public function testProcessSuccessWhenExpressionReturnsObject()
    {
        $container = new ContainerBuilder();

        $container->register('waldo', Waldo::class);

        $container
            ->register('wobble', Wobble::class)
            ->setArguments([new Expression("service('waldo')")]);

        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->addToAssertionCount(1);
    }

    public function testProcessHandleMixedEnvPlaceholder()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid definition for service "foobar": argument 1 of "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass\BarMethodCall::setArray()" accepts "array", "string" passed.');

        $container = new ContainerBuilder(new EnvPlaceholderParameterBag([
            'ccc' => '%env(FOO)%',
        ]));

        $container
            ->register('foobar', BarMethodCall::class)
            ->addMethodCall('setArray', ['foo%ccc%']);

        (new CheckTypeDeclarationsPass(true))->process($container);
    }

    public function testProcessHandleMultipleEnvPlaceholder()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid definition for service "foobar": argument 1 of "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass\BarMethodCall::setArray()" accepts "array", "string" passed.');

        $container = new ContainerBuilder(new EnvPlaceholderParameterBag([
            'ccc' => '%env(FOO)%',
            'fcy' => '%env(int:BAR)%',
        ]));

        $container
            ->register('foobar', BarMethodCall::class)
            ->addMethodCall('setArray', ['%ccc%%fcy%']);

        (new CheckTypeDeclarationsPass(true))->process($container);
    }

    public function testProcessHandleExistingEnvPlaceholder()
    {
        putenv('ARRAY={"foo":"bar"}');

        $container = new ContainerBuilder(new EnvPlaceholderParameterBag([
            'ccc' => '%env(json:ARRAY)%',
        ]));

        $container
            ->register('foobar', BarMethodCall::class)
            ->addMethodCall('setArray', ['%ccc%']);

        (new ResolveParameterPlaceHoldersPass())->process($container);
        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->addToAssertionCount(1);

        putenv('ARRAY=');
    }

    public function testProcessHandleNotFoundEnvPlaceholder()
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag([
            'ccc' => '%env(json:ARRAY)%',
        ]));

        $container
            ->register('foobar', BarMethodCall::class)
            ->addMethodCall('setArray', ['%ccc%']);

        (new ResolveParameterPlaceHoldersPass())->process($container);
        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->addToAssertionCount(1);
    }

    public function testProcessSkipSkippedIds()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foobar', BarMethodCall::class)
            ->addMethodCall('setArray', ['a string']);

        (new CheckTypeDeclarationsPass(true, ['foobar' => true]))->process($container);

        $this->addToAssertionCount(1);
    }

    public function testProcessSkipsDeprecatedDefinitions()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foobar', Deprecated::class)
            ->setDeprecated('foo/bar', '1.2.3', '')
        ;

        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->addToAssertionCount(1);
    }

    public function testProcessHandleClosureForCallable()
    {
        $closureDefinition = new Definition(\Closure::class);
        $closureDefinition->setFactory([\Closure::class, 'fromCallable']);
        $closureDefinition->setArguments(['strlen']);

        $container = new ContainerBuilder();
        $container
            ->register('foobar', BarMethodCall::class)
            ->addMethodCall('setCallable', [$closureDefinition]);

        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->addToAssertionCount(1);
    }

    public function testProcessSuccessWhenPassingServiceClosureArgumentToCallable()
    {
        $container = new ContainerBuilder();

        $container->register('bar', BarMethodCall::class)
            ->addMethodCall('setCallable', [new ServiceClosureArgument(new Reference('foo'))]);

        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->addToAssertionCount(1);
    }

    public function testProcessSuccessWhenPassingServiceClosureArgumentToClosure()
    {
        $container = new ContainerBuilder();

        $container->register('bar', BarMethodCall::class)
            ->addMethodCall('setClosure', [new ServiceClosureArgument(new Reference('foo'))]);

        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->addToAssertionCount(1);
    }

    public function testExpressionLanguageWithSyntheticService()
    {
        $container = new ContainerBuilder();

        $container->register('synthetic')
            ->setSynthetic(true);
        $container->register('baz', \stdClass::class)
            ->addArgument(new Reference('synthetic'));
        $container->register('bar', Bar::class)
            ->addArgument(new Expression('service("baz").getStdClass()'));

        (new CheckTypeDeclarationsPass())->process($container);

        $this->addToAssertionCount(1);
    }

    public function testProcessResolveParameters()
    {
        putenv('ARRAY={"foo":"bar"}');

        $container = new ContainerBuilder(new EnvPlaceholderParameterBag([
            'env_array_param' => '%env(json:ARRAY)%',
        ]));
        $container->setParameter('array_param', ['foobar']);
        $container->setParameter('string_param', 'ccc');

        $definition = $container->register('foobar', BarMethodCall::class);
        $definition
            ->addMethodCall('setArray', ['%array_param%'])
            ->addMethodCall('setString', ['%string_param%']);

        (new ResolveParameterPlaceHoldersPass())->process($container);

        $definition->addMethodCall('setArray', ['%env_array_param%']);

        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->addToAssertionCount(1);

        putenv('ARRAY=');
    }

    public function testUnionTypePassesWithReference()
    {
        $container = new ContainerBuilder();

        $container->register('foo', Foo::class);
        $container->register('union', UnionConstructor::class)
            ->setArguments([new Reference('foo')]);

        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->addToAssertionCount(1);
    }

    public function testUnionTypePassesWithBuiltin()
    {
        $container = new ContainerBuilder();

        $container->register('union', UnionConstructor::class)
            ->setArguments([42]);

        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->addToAssertionCount(1);
    }

    public function testUnionTypePassesWithFalse()
    {
        $container = new ContainerBuilder();

        $container->register('union', UnionConstructor::class)
            ->setFactory([UnionConstructor::class, 'create'])
            ->setArguments([false]);

        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->addToAssertionCount(1);
    }

    public function testUnionTypeFailsWithReference()
    {
        $container = new ContainerBuilder();

        $container->register('waldo', Waldo::class);
        $container->register('union', UnionConstructor::class)
            ->setArguments([new Reference('waldo')]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid definition for service "union": argument 1 of "Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CheckTypeDeclarationsPass\\UnionConstructor::__construct()" accepts "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass\Foo|int", "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass\Waldo" passed.');

        (new CheckTypeDeclarationsPass(true))->process($container);
    }

    public function testUnionTypeFailsWithBuiltin()
    {
        $container = new ContainerBuilder();

        $container->register('union', UnionConstructor::class)
            ->setArguments([[1, 2, 3]]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid definition for service "union": argument 1 of "Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CheckTypeDeclarationsPass\\UnionConstructor::__construct()" accepts "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass\Foo|int", "array" passed.');

        (new CheckTypeDeclarationsPass(true))->process($container);
    }

    public function testUnionTypeWithFalseFailsWithReference()
    {
        $container = new ContainerBuilder();

        $container->register('waldo', Waldo::class);
        $container->register('union', UnionConstructor::class)
            ->setFactory([UnionConstructor::class, 'create'])
            ->setArguments([new Reference('waldo')]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid definition for service "union": argument 1 of "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass\UnionConstructor::create()" accepts "array|false", "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass\Waldo" passed.');

        (new CheckTypeDeclarationsPass(true))->process($container);
    }

    public function testUnionTypeWithFalseFailsWithTrue()
    {
        $container = new ContainerBuilder();

        $container->register('waldo', Waldo::class);
        $container->register('union', UnionConstructor::class)
            ->setFactory([UnionConstructor::class, 'create'])
            ->setArguments([true]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid definition for service "union": argument 1 of "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass\UnionConstructor::create()" accepts "array|false", "bool" passed.');

        (new CheckTypeDeclarationsPass(true))->process($container);
    }

    public function testReferencePassesMixed()
    {
        $container = new ContainerBuilder();

        $container->register('waldo', Waldo::class);
        $container->register('union', UnionConstructor::class)
            ->setFactory([UnionConstructor::class, 'make'])
            ->setArguments([new Reference('waldo')]);

        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->addToAssertionCount(1);
    }

    public function testIntersectionTypePassesWithReference()
    {
        $container = new ContainerBuilder();

        $container->register('foo', WaldoFoo::class);
        $container->register('intersection', IntersectionConstructor::class)
            ->setArguments([new Reference('foo')]);

        (new CheckTypeDeclarationsPass(true))->process($container);

        $this->addToAssertionCount(1);
    }

    public function testIntersectionTypeFailsWithReference()
    {
        $container = new ContainerBuilder();

        $container->register('waldo', Waldo::class);
        $container->register('intersection', IntersectionConstructor::class)
            ->setArguments([new Reference('waldo')]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid definition for service "intersection": argument 1 of "Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CheckTypeDeclarationsPass\\IntersectionConstructor::__construct()" accepts "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass\Foo&Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass\WaldoInterface", "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass\Waldo" passed.');

        (new CheckTypeDeclarationsPass(true))->process($container);
    }

    public function testCallableClass()
    {
        $container = new ContainerBuilder();
        $definition = $container->register('foo', CallableClass::class);
        $definition->addMethodCall('callMethod', [123]);

        (new CheckTypeDeclarationsPass())->process($container);

        $this->addToAssertionCount(1);
    }

    public function testIgnoreDefinitionFactoryArgument()
    {
        $container = new ContainerBuilder();
        $container->register('bar', Bar::class)
            ->setArguments([
                (new Definition(Foo::class))
                    ->setFactory([Foo::class, 'createStdClass']),
            ]);

        (new CheckTypeDeclarationsPass())->process($container);

        $this->addToAssertionCount(1);
    }
}

class CallableClass
{
    public function __call($name, $arguments)
    {
    }
}
