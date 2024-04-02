<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Controller\ArgumentResolver;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Attribute\MapSessionParameter;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\SessionParameterValueResolver;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class SessionParameterValueResolverTest extends TestCase
{
    private ValueResolverInterface $resolver;

    private Request $request;

    protected function setUp(): void
    {
        $this->resolver = new SessionParameterValueResolver();

        $session = new Session(new MockArraySessionStorage());
        $this->request = Request::create('/');
        $this->request->setSession($session);
    }

    public function testSkipWhenNoAttribute()
    {
        $metadata = new ArgumentMetadata('browsingContext', 'string', false, true, false);

        $this->assertSame([], $this->resolver->resolve($this->request, $metadata));
    }

    public function testSkipWhenNoSession()
    {
        $metadata = new ArgumentMetadata('MySessionObject', BasicSessionParameter::class, false, false, false, attributes: [new MapSessionParameter()]);

        $this->assertSame([], $this->resolver->resolve(Request::create('/'), $metadata));
    }

    /**
     * @dataProvider invalidArgumentTypeProvider
     */
    public function testResolvingWithInvalidArgumentType(ArgumentMetadata $metadata, string $exceptionMessage)
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage($exceptionMessage);

        $this->resolver->resolve($this->request, $metadata);
    }

    /**
     * @return iterable<string, array{ArgumentMetadata,string}>
     */
    public static function invalidArgumentTypeProvider(): iterable
    {
        yield 'untyped parameter' => [
            new ArgumentMetadata('MySessionObject', null, false, false, false, attributes: [new MapSessionParameter()]),
            '#[MapSessionParameter] cannot be used on controller argument "$MySessionObject": "" is not a class or interface name.',
        ];

        yield 'scalar parameter' => [
            new ArgumentMetadata('MySessionObject', 'string', false, false, false, attributes: [new MapSessionParameter()]),
            '#[MapSessionParameter] cannot be used on controller argument "$MySessionObject": "string" is not a class or interface name.',
        ];

        yield 'variadic scalar parameter' => [
            new ArgumentMetadata('MySessionObject', 'string', true, false, false, true, attributes: [new MapSessionParameter()]),
            '#[MapSessionParameter] cannot be used on controller argument "$MySessionObject": "string" is not a class or interface name.',
        ];

        yield 'interface without default value and not nullable' => [
            new ArgumentMetadata('MySessionObject', SessionParameterInterface::class, false, false, false, attributes: [new MapSessionParameter()]),
            '#[MapSessionParameter] cannot be used on controller argument "$MySessionObject": "Symfony\Component\HttpKernel\Tests\Controller\ArgumentResolver\SessionParameterInterface" is an interface, you need to make the parameter nullable or provide a default value.',
        ];
    }

    /**
     * @dataProvider validDataProvider
     */
    public function testResolvingSuccessfully(ArgumentMetadata $metadata, ?string $expectedType)
    {
        $result = $this->resolver->resolve($this->request, $metadata);
        $this->assertCount(1, $result);

        if (null === $expectedType) {
            $this->assertNull($result[0]);
        } else {
            $this->assertInstanceOf($expectedType, $result[0]);
        }
    }

    /**
     * @return iterable<string, array{ArgumentMetadata,string|null}>
     */
    public static function validDataProvider(): iterable
    {
        yield 'typed parameter with properties' => [
            new ArgumentMetadata('MySessionObject', BasicSessionParameter::class, false, false, false, attributes: [new MapSessionParameter()]),
            BasicSessionParameter::class,
        ];
        yield 'typed parameter without properties' => [
            new ArgumentMetadata('MySessionObject', EmptySessionParameter::class, false, false, false, attributes: [new MapSessionParameter()]),
            EmptySessionParameter::class,
        ];
        yield 'stdClass parameter' => [
            new ArgumentMetadata('MySessionObject', \stdClass::class, false, false, false, attributes: [new MapSessionParameter()]),
            \stdClass::class,
        ];
        yield 'variadic parameter' => [
            new ArgumentMetadata('MySessionObject', BasicSessionParameter::class, true, false, false, attributes: [new MapSessionParameter()]),
            BasicSessionParameter::class,
        ];
        yield 'nullable parameter' => [
            new ArgumentMetadata('MySessionObject', BasicSessionParameter::class, false, false, false, true, attributes: [new MapSessionParameter()]),
            BasicSessionParameter::class,
        ];
        yield 'default to null parameter' => [
            new ArgumentMetadata('MySessionObject', BasicSessionParameter::class, false, true, null, true, attributes: [new MapSessionParameter()]),
            null,
        ];
        yield 'nullable interface without default value' => [
            new ArgumentMetadata('MySessionObject', SessionParameterInterface::class, false, true, null, true, attributes: [new MapSessionParameter()]),
            null,
        ];
        yield 'interface with default value' => [
            new ArgumentMetadata('MySessionObject', SessionParameterInterface::class, false, true, new BasicSessionParameter(), false, attributes: [new MapSessionParameter()]),
            BasicSessionParameter::class,
        ];
    }

    public function testWithoutNameParameter()
    {
        $metadata = new ArgumentMetadata('MySessionObject', BasicSessionParameter::class, false, false, false, attributes: [new MapSessionParameter()]);
        $this->resolver->resolve($this->request, $metadata);
        $this->assertEquals(['MySessionObject'], array_keys($this->request->getSession()->all()));
    }

    /**
     * @dataProvider sessionNameProvider
     */
    public function testNameParameter(?string $name, string $sessionKey)
    {
        $metadata = new ArgumentMetadata('MySessionObject', BasicSessionParameter::class, false, false, false, attributes: [
            new MapSessionParameter($name),
        ]);
        $this->resolver->resolve($this->request, $metadata);
        $this->assertEquals([$sessionKey], array_keys($this->request->getSession()->all()));
    }

    /**
     * @return iterable<?string, string>
     */
    public static function sessionNameProvider(): iterable
    {
        yield 'no value' => [null, 'MySessionObject'];
        yield 'same as class' => ['MySessionObject', 'MySessionObject'];
        yield 'empty' => ['', ''];
        yield 'other' => ['other', 'other'];
    }

    public function testResolvingCorrectTypeSuccessfully()
    {
        $this->request->getSession()->set('MySessionObject', new ExtendingEmptySessionParameter());
        $result = $this->resolver->resolve($this->request, new ArgumentMetadata('MySessionObject', EmptySessionParameter::class, false, false, false, attributes: [new MapSessionParameter()]));

        $this->assertCount(1, $result);
        $this->assertInstanceOf(EmptySessionParameter::class, $result[0]);
    }

    public function testResolvingCorrectInterfaceSuccessfully()
    {
        $this->request->getSession()->set('MySessionObject', new BasicSessionParameter());
        $result = $this->resolver->resolve($this->request, new ArgumentMetadata('MySessionObject', SessionParameterInterface::class, false, false, false, isNullable: true, attributes: [new MapSessionParameter()]));

        $this->assertCount(1, $result);
        $this->assertInstanceOf(SessionParameterInterface::class, $result[0]);
    }

    public function testResolvingIncorrectTypeFailure()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('#[MapSessionParameter] cannot be used to map controller argument "$MySessionObject": the session contains a value of type "Symfony\Component\HttpKernel\Tests\Controller\ArgumentResolver\BasicSessionParameter" which is not an instance of "Symfony\Component\HttpKernel\Tests\Controller\ArgumentResolver\EmptySessionParameter".');

        $this->request->getSession()->set('MySessionObject', new BasicSessionParameter());
        $this->resolver->resolve($this->request, new ArgumentMetadata('MySessionObject', EmptySessionParameter::class, false, false, false, attributes: [new MapSessionParameter()]));
    }

    public function testResolvingIncorrectInterfaceFailure()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('#[MapSessionParameter] cannot be used to map controller argument "$MySessionObject": the session contains a value of type "Symfony\Component\HttpKernel\Tests\Controller\ArgumentResolver\EmptySessionParameter" which is not an instance of "Symfony\Component\HttpKernel\Tests\Controller\ArgumentResolver\SessionParameterInterface".');

        $this->request->getSession()->set('MySessionObject', new EmptySessionParameter());
        $this->resolver->resolve($this->request, new ArgumentMetadata('MySessionObject', SessionParameterInterface::class, false, false, false, isNullable: true, attributes: [new MapSessionParameter()]));
    }
}

class BasicSessionParameter implements SessionParameterInterface
{
    public $locale;
}

class EmptySessionParameter
{
}

class ExtendingEmptySessionParameter extends EmptySessionParameter
{
}

interface SessionParameterInterface
{
}
