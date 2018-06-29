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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CheckTypeHintsPass;
use Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeHintsPass\Bar;
use Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeHintsPass\BarOptionalArgument;
use Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeHintsPass\BarOptionalArgumentNotNull;
use Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeHintsPass\BarMethodCall;
use Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeHintsPass\Foo;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Julien Maulny <jmaulny@darkmira.fr>
 */
class CheckTypeHintsPassTest extends TestCase
{
    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid definition for service "bar": argument 0 of "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeHintsPass\Bar::__construct" requires a "stdClass", "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeHintsPass\Foo" passed
     */
    public function testProcessThrowsExceptionOnInvalidTypeHintsConstructorArguments()
    {
        $container = new ContainerBuilder();

        $container->register('foo', Foo::class);
        $container->register('bar', Bar::class)
            ->addArgument(new Reference('foo'));

        (new CheckTypeHintsPass(true))->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid definition for service "bar": argument 0 of "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeHintsPass\BarMethodCall::setFoo" requires a "stdClass", "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeHintsPass\Foo" passed
     */
    public function testProcessThrowsExceptionOnInvalidTypeHintsMethodCallArguments()
    {
        $container = new ContainerBuilder();

        $container->register('foo', Foo::class);
        $container->register('bar', BarMethodCall::class)
            ->addMethodCall('setFoo', array(new Reference('foo')));

        (new CheckTypeHintsPass(true))->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid definition for service "bar": argument 0 of "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeHintsPass\Bar::__construct" requires a "stdClass", "NULL" passed
     */
    public function testProcessFailsWhenPassingNullToRequiredArgument()
    {
        $container = new ContainerBuilder();

        $container->register('bar', Bar::class)
            ->addArgument(null);

        (new CheckTypeHintsPass(true))->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid definition for service "bar": "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeHintsPass\Bar::__construct()" requires 1 arguments, 0 passed
     */
    public function testProcessThrowsExceptionWhenMissingArgumentsInConstructor()
    {
        $container = new ContainerBuilder();

        $container->register('bar', Bar::class);

        (new CheckTypeHintsPass(true))->process($container);
    }

    public function testProcessSuccessWhenPassingTooManyArgumentInConstructor()
    {
        $container = new ContainerBuilder();

        $container->register('foo', \stdClass::class);
        $container->register('bar', Bar::class)
            ->addArgument(new Reference('foo'))
            ->addArgument(new Reference('foo'));

        (new CheckTypeHintsPass(true))->process($container);

        $this->addToAssertionCount(1);
    }

    public function testProcessRegisterWithClassName()
    {
        $container = new ContainerBuilder();

        $container->register(Foo::class, Foo::class);

        (new CheckTypeHintsPass(true))->process($container);

        $this->assertInstanceOf(Foo::class, $container->get(Foo::class));
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid definition for service "bar": "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeHintsPass\BarMethodCall::setFoo()" requires 1 arguments, 0 passed
     */
    public function testProcessThrowsExceptionWhenMissingArgumentsInMethodCall()
    {
        $container = new ContainerBuilder();

        $container->register('foo', \stdClass::class);
        $container->register('bar', BarMethodCall::class)
            ->addArgument(new Reference('foo'))
            ->addMethodCall('setFoo', array());

        (new CheckTypeHintsPass(true))->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid definition for service "bar": argument 1 of "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeHintsPass\BarMethodCall::setFoosVariadic" requires a "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeHintsPass\Foo", "stdClass" passed
     */
    public function testProcessVariadicFails()
    {
        $container = new ContainerBuilder();

        $container->register('stdClass', \stdClass::class);
        $container->register('foo', Foo::class);
        $container->register('bar', BarMethodCall::class)
            ->addMethodCall('setFoosVariadic', array(
                new Reference('foo'),
                new Reference('foo'),
                new Reference('stdClass'),
            ));

        (new CheckTypeHintsPass(true))->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid definition for service "bar": argument 0 of "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeHintsPass\BarMethodCall::setFoosVariadic" requires a "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeHintsPass\Foo", "stdClass" passed
     */
    public function testProcessVariadicFailsOnPassingBadTypeOnAnotherArgument()
    {
        $container = new ContainerBuilder();

        $container->register('stdClass', \stdClass::class);
        $container->register('bar', BarMethodCall::class)
            ->addMethodCall('setFoosVariadic', array(
                new Reference('stdClass'),
            ));

        (new CheckTypeHintsPass(true))->process($container);
    }

    public function testProcessVariadicSuccess()
    {
        $container = new ContainerBuilder();

        $container->register('foo', Foo::class);
        $container->register('bar', BarMethodCall::class)
            ->addMethodCall('setFoosVariadic', array(
                new Reference('foo'),
                new Reference('foo'),
                new Reference('foo'),
            ));

        (new CheckTypeHintsPass(true))->process($container);

        $this->assertInstanceOf(Foo::class, $container->get('bar')->foo);
    }

    public function testProcessSuccessWhenNotUsingOptionalArgument()
    {
        $container = new ContainerBuilder();

        $container->register('foo', Foo::class);
        $container->register('bar', BarMethodCall::class)
            ->addMethodCall('setFoosOptional', array(
                new Reference('foo'),
            ));

        (new CheckTypeHintsPass(true))->process($container);

        $this->assertInstanceOf(Foo::class, $container->get('bar')->foo);
    }

    public function testProcessSuccessWhenUsingOptionalArgumentWithGoodType()
    {
        $container = new ContainerBuilder();

        $container->register('foo', Foo::class);
        $container->register('bar', BarMethodCall::class)
            ->addMethodCall('setFoosOptional', array(
                new Reference('foo'),
                new Reference('foo'),
            ));

        (new CheckTypeHintsPass(true))->process($container);

        $this->assertInstanceOf(Foo::class, $container->get('bar')->foo);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid definition for service "bar": argument 1 of "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeHintsPass\BarMethodCall::setFoosOptional" requires a "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeHintsPass\Foo", "stdClass" passed
     */
    public function testProcessFailsWhenUsingOptionalArgumentWithBadType()
    {
        $container = new ContainerBuilder();

        $container->register('stdClass', \stdClass::class);
        $container->register('foo', Foo::class);
        $container->register('bar', BarMethodCall::class)
            ->addMethodCall('setFoosOptional', array(
                new Reference('foo'),
                new Reference('stdClass'),
            ));

        (new CheckTypeHintsPass(true))->process($container);
    }

    public function testProcessSuccessWhenPassingNullToOptional()
    {
        $container = new ContainerBuilder();

        $container->register('bar', BarOptionalArgument::class)
            ->addArgument(null);

        (new CheckTypeHintsPass(true))->process($container);

        $this->assertNull($container->get('bar')->foo);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid definition for service "bar": argument 0 of "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeHintsPass\BarOptionalArgumentNotNull::__construct" requires a "int", "NULL" passed
     */
    public function testProcessSuccessWhenPassingNullToOptionalThatDoesNotAcceptNull()
    {
        $container = new ContainerBuilder();

        $container->register('bar', BarOptionalArgumentNotNull::class)
            ->addArgument(null);

        (new CheckTypeHintsPass(true))->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid definition for service "bar": argument 0 of "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeHintsPass\BarOptionalArgument::__construct" requires a "stdClass", "string" passed
     */
    public function testProcessFailsWhenPassingBadTypeToOptional()
    {
        $container = new ContainerBuilder();

        $container->register('bar', BarOptionalArgument::class)
            ->addArgument('string instead of stdClass');

        (new CheckTypeHintsPass(true))->process($container);

        $this->assertNull($container->get('bar')->foo);
    }

    public function testProcessSuccessScalarType()
    {
        $container = new ContainerBuilder();

        $container->register('bar', BarMethodCall::class)
            ->addMethodCall('setScalars', array(
                1,
                'string',
            ));

        (new CheckTypeHintsPass(true))->process($container);

        $this->assertInstanceOf(BarMethodCall::class, $container->get('bar'));
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid definition for service "bar": argument 0 of "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeHintsPass\Bar::__construct" requires a "stdClass", "integer" passed
     */
    public function testProcessFailsOnPassingScalarTypeToConstructorTypeHintedWithClass()
    {
        $container = new ContainerBuilder();

        $container->register('bar', Bar::class)
            ->addArgument(1);

        (new CheckTypeHintsPass(true))->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid definition for service "bar": argument 0 of "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeHintsPass\BarMethodCall::setFoo" requires a "stdClass", "string" passed
     */
    public function testProcessFailsOnPassingScalarTypeToMethodTypeHintedWithClass()
    {
        $container = new ContainerBuilder();

        $container->register('bar', BarMethodCall::class)
            ->addMethodCall('setFoo', array(
                'builtin type instead of class',
            ));

        (new CheckTypeHintsPass(true))->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid definition for service "bar": argument 0 of "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeHintsPass\BarMethodCall::setScalars" requires a "int", "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeHintsPass\Foo" passed
     */
    public function testProcessFailsOnPassingClassToScalarTypeHintedParameter()
    {
        $container = new ContainerBuilder();

        $container->register('foo', Foo::class);
        $container->register('bar', BarMethodCall::class)
            ->addMethodCall('setScalars', array(
                new Reference('foo'),
                new Reference('foo'),
            ));

        (new CheckTypeHintsPass(true))->process($container);
    }

    /**
     * Strict mode not yet handled.
     */
    public function testProcessSuccessOnPassingBadScalarType()
    {
        $container = new ContainerBuilder();

        $container->register('bar', BarMethodCall::class)
            ->addMethodCall('setScalars', array(
                1,
                true,
            ));

        (new CheckTypeHintsPass(true))->process($container);

        $this->assertInstanceOf(BarMethodCall::class, $container->get('bar'));
    }

    /**
     * Strict mode not yet handled.
     */
    public function testProcessSuccessPassingBadScalarTypeOptionalArgument()
    {
        $container = new ContainerBuilder();

        $container->register('bar', BarMethodCall::class)
            ->addMethodCall('setScalars', array(
                1,
                'string',
                'string instead of optional boolean',
            ));

        (new CheckTypeHintsPass(true))->process($container);

        $this->assertInstanceOf(BarMethodCall::class, $container->get('bar'));
    }

    public function testProcessSuccessWhenPassingArray()
    {
        $container = new ContainerBuilder();

        $container->register('bar', BarMethodCall::class)
            ->addMethodCall('setArray', array(
                array(),
            ));

        (new CheckTypeHintsPass(true))->process($container);

        $this->assertInstanceOf(BarMethodCall::class, $container->get('bar'));
    }

    public function testProcessSuccessWhenPassingIntegerToArrayTypeHintedParameter()
    {
        $container = new ContainerBuilder();

        $container->register('bar', BarMethodCall::class)
            ->addMethodCall('setArray', array(
                1,
            ));

        (new CheckTypeHintsPass(true))->process($container);

        $this->addToAssertionCount(1);
    }

    public function testProcessSuccessWhenPassingAnIteratorArgumentToIterable()
    {
        $container = new ContainerBuilder();

        $container->register('bar', BarMethodCall::class)
            ->addMethodCall('setIterable', array(
                new IteratorArgument(array()),
            ));

        (new CheckTypeHintsPass(true))->process($container);

        $this->addToAssertionCount(1);
    }

    public function testProcessFactory()
    {
        $container = new ContainerBuilder();

        $container->register('foo', Foo::class);
        $container->register('bar', Bar::class)
            ->setFactory(array(
                new Reference('foo'),
                'createBar',
            ));

        (new CheckTypeHintsPass(true))->process($container);

        $this->assertInstanceOf(Bar::class, $container->get('bar'));
    }

    public function testProcessFactoryWhithClassName()
    {
        $container = new ContainerBuilder();

        $container->register(Foo::class, Foo::class);
        $container->register(Bar::class, Bar::class)
            ->setFactory(array(
                new Reference(Foo::class),
                'createBar',
            ));

        (new CheckTypeHintsPass(true))->process($container);

        $this->assertInstanceOf(Bar::class, $container->get(Bar::class));
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid definition for service "bar": argument 0 of "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeHintsPass\Foo::createBarArguments" requires a "stdClass", "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeHintsPass\Foo" passed
     */
    public function testProcessFactoryFailsOnInvalidParameterType()
    {
        $container = new ContainerBuilder();

        $container->register('foo', Foo::class);
        $container->register('bar', Bar::class)
            ->addArgument(new Reference('foo'))
            ->setFactory(array(
                new Reference('foo'),
                'createBarArguments',
            ));

        (new CheckTypeHintsPass(true))->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid definition for service "bar": argument 1 of "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeHintsPass\Foo::createBarArguments" requires a "stdClass", "Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeHintsPass\Foo" passed
     */
    public function testProcessFactoryFailsOnInvalidParameterTypeOptional()
    {
        $container = new ContainerBuilder();

        $container->register('stdClass', \stdClass::class);
        $container->register('foo', Foo::class);
        $container->register('bar', Bar::class)
            ->addArgument(new Reference('stdClass'))
            ->addArgument(new Reference('foo'))
            ->setFactory(array(
                new Reference('foo'),
                'createBarArguments',
            ));

        (new CheckTypeHintsPass(true))->process($container);
    }

    public function testProcessFactorySuccessOnValidTypes()
    {
        $container = new ContainerBuilder();

        $container->register('stdClass', \stdClass::class);
        $container->register('foo', Foo::class);
        $container->register('bar', Bar::class)
            ->addArgument(new Reference('stdClass'))
            ->addArgument(new Reference('stdClass'))
            ->setFactory(array(
                new Reference('foo'),
                'createBarArguments',
            ));

        (new CheckTypeHintsPass(true))->process($container);

        $this->addToAssertionCount(1);
    }

    public function testProcessFactoryCallbackSuccessOnValidType()
    {
        $container = new ContainerBuilder();

        $container->register('bar', \DateTime::class)
            ->setFactory('date_create');

        (new CheckTypeHintsPass(true))->process($container);

        $this->assertInstanceOf(\DateTime::class, $container->get('bar'));
    }

    public function testProcessDoesNotLoadCodeByDefault()
    {
        $container = new ContainerBuilder();

        $container->register('foo', FooNotExisting::class);
        $container->register('bar', BarNotExisting::class)
            ->addArgument(new Reference('foo'))
            ->addMethodCall('setFoo', array(
                new Reference('foo'),
                'string',
                1,
            ));

        (new CheckTypeHintsPass())->process($container);

        $this->addToAssertionCount(1);
    }

    public function testProcessFactoryDoesNotLoadCodeByDefault()
    {
        $container = new ContainerBuilder();

        $container->register('foo', FooNotExisting::class);
        $container->register('bar', BarNotExisting::class)
            ->setFactory(array(
                new Reference('foo'),
                'notExistingMethod',
            ));

        (new CheckTypeHintsPass())->process($container);

        $this->addToAssertionCount(1);
    }

    public function testProcessPassingBuiltinTypeDoesNotLoadCodeByDefault()
    {
        $container = new ContainerBuilder();

        $container->register('bar', BarNotExisting::class)
            ->addArgument(1);

        (new CheckTypeHintsPass())->process($container);

        $this->addToAssertionCount(1);
    }

    public function testProcessDoesNotThrowsExceptionOnValidTypeHints()
    {
        $container = new ContainerBuilder();

        $container->register('foo', \stdClass::class);
        $container->register('bar', Bar::class)
            ->addArgument(new Reference('foo'));

        (new CheckTypeHintsPass(true))->process($container);

        $this->assertInstanceOf(\stdClass::class, $container->get('bar')->foo);
    }
}
