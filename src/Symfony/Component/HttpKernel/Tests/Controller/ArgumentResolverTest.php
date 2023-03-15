<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\DefaultValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestAttributeValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadataFactory;
use Symfony\Component\HttpKernel\Exception\ResolverNotFoundException;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\ExtendingRequest;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\ExtendingSession;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\NullableController;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\VariadicController;

class ArgumentResolverTest extends TestCase
{
    public static function getResolver(array $chainableResolvers = [], array $namedResolvers = null): ArgumentResolver
    {
        if (null !== $namedResolvers) {
            $namedResolvers = new ServiceLocator(array_map(fn ($resolver) => fn () => $resolver, $namedResolvers));
        }

        return new ArgumentResolver(new ArgumentMetadataFactory(), $chainableResolvers, $namedResolvers);
    }

    public function testDefaultState()
    {
        $this->assertEquals(self::getResolver(), new ArgumentResolver());
        $this->assertNotEquals(self::getResolver(), new ArgumentResolver(null, [new RequestAttributeValueResolver()]));
    }

    public function testGetArguments()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = [new self(), 'controllerWithFoo'];

        $this->assertEquals(['foo'], self::getResolver()->getArguments($request, $controller), '->getArguments() returns an array of arguments for the controller method');
    }

    public function testGetArgumentsReturnsEmptyArrayWhenNoArguments()
    {
        $request = Request::create('/');
        $controller = [new self(), 'controllerWithoutArguments'];

        $this->assertEquals([], self::getResolver()->getArguments($request, $controller), '->getArguments() returns an empty array if the method takes no arguments');
    }

    public function testGetArgumentsUsesDefaultValue()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = [new self(), 'controllerWithFooAndDefaultBar'];

        $this->assertEquals(['foo', null], self::getResolver()->getArguments($request, $controller), '->getArguments() uses default values if present');
    }

    public function testGetArgumentsOverrideDefaultValueByRequestAttribute()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', 'bar');
        $controller = [new self(), 'controllerWithFooAndDefaultBar'];

        $this->assertEquals(['foo', 'bar'], self::getResolver()->getArguments($request, $controller), '->getArguments() overrides default values if provided in the request attributes');
    }

    public function testGetArgumentsFromClosure()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = function ($foo) {};

        $this->assertEquals(['foo'], self::getResolver()->getArguments($request, $controller));
    }

    public function testGetArgumentsUsesDefaultValueFromClosure()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = function ($foo, $bar = 'bar') {};

        $this->assertEquals(['foo', 'bar'], self::getResolver()->getArguments($request, $controller));
    }

    public function testGetArgumentsFromInvokableObject()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = new self();

        $this->assertEquals(['foo', null], self::getResolver()->getArguments($request, $controller));

        // Test default bar overridden by request attribute
        $request->attributes->set('bar', 'bar');

        $this->assertEquals(['foo', 'bar'], self::getResolver()->getArguments($request, $controller));
    }

    public function testGetArgumentsFromFunctionName()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('foobar', 'foobar');
        $controller = __NAMESPACE__.'\controller_function';

        $this->assertEquals(['foo', 'foobar'], self::getResolver()->getArguments($request, $controller));
    }

    public function testGetArgumentsFailsOnUnresolvedValue()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('foobar', 'foobar');
        $controller = [new self(), 'controllerWithFooBarFoobar'];

        try {
            self::getResolver()->getArguments($request, $controller);
            $this->fail('->getArguments() throws a \RuntimeException exception if it cannot determine the argument value');
        } catch (\Exception $e) {
            $this->assertInstanceOf(\RuntimeException::class, $e, '->getArguments() throws a \RuntimeException exception if it cannot determine the argument value');
        }
    }

    public function testGetArgumentsInjectsRequest()
    {
        $request = Request::create('/');
        $controller = [new self(), 'controllerWithRequest'];

        $this->assertEquals([$request], self::getResolver()->getArguments($request, $controller), '->getArguments() injects the request');
    }

    public function testGetArgumentsInjectsExtendingRequest()
    {
        $request = ExtendingRequest::create('/');
        $controller = [new self(), 'controllerWithExtendingRequest'];

        $this->assertEquals([$request], self::getResolver()->getArguments($request, $controller), '->getArguments() injects the request when extended');
    }

    public function testGetVariadicArguments()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', ['foo', 'bar']);
        $controller = [new VariadicController(), 'action'];

        $this->assertEquals(['foo', 'foo', 'bar'], self::getResolver()->getArguments($request, $controller));
    }

    public function testGetVariadicArgumentsWithoutArrayInRequest()
    {
        $this->expectException(\InvalidArgumentException::class);
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', 'foo');
        $controller = [new VariadicController(), 'action'];

        self::getResolver()->getArguments($request, $controller);
    }

    /**
     * @group legacy
     */
    public function testGetArgumentWithoutArray()
    {
        $this->expectException(\InvalidArgumentException::class);
        $valueResolver = $this->createMock(ArgumentValueResolverInterface::class);
        $resolver = self::getResolver([$valueResolver]);

        $valueResolver->expects($this->any())->method('supports')->willReturn(true);
        $valueResolver->expects($this->any())->method('resolve')->willReturn([]);

        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', 'foo');
        $controller = $this->controllerWithFooAndDefaultBar(...);
        $resolver->getArguments($request, $controller);
    }

    public function testIfExceptionIsThrownWhenMissingAnArgument()
    {
        $this->expectException(\RuntimeException::class);
        $request = Request::create('/');
        $controller = $this->controllerWithFoo(...);

        self::getResolver()->getArguments($request, $controller);
    }

    public function testGetNullableArguments()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', new \stdClass());
        $request->attributes->set('last', 'last');
        $controller = [new NullableController(), 'action'];

        $this->assertEquals(['foo', new \stdClass(), 'value', 'last'], self::getResolver()->getArguments($request, $controller));
    }

    public function testGetNullableArgumentsWithDefaults()
    {
        $request = Request::create('/');
        $request->attributes->set('last', 'last');
        $controller = [new NullableController(), 'action'];

        $this->assertEquals([null, null, 'value', 'last'], self::getResolver()->getArguments($request, $controller));
    }

    public function testGetSessionArguments()
    {
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $controller = $this->controllerWithSession(...);

        $this->assertEquals([$session], self::getResolver()->getArguments($request, $controller));
    }

    public function testGetSessionArgumentsWithExtendedSession()
    {
        $session = new ExtendingSession(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $controller = $this->controllerWithExtendingSession(...);

        $this->assertEquals([$session], self::getResolver()->getArguments($request, $controller));
    }

    public function testGetSessionArgumentsWithInterface()
    {
        $session = $this->createMock(SessionInterface::class);
        $request = Request::create('/');
        $request->setSession($session);
        $controller = $this->controllerWithSessionInterface(...);

        $this->assertEquals([$session], self::getResolver()->getArguments($request, $controller));
    }

    public function testGetSessionMissMatchWithInterface()
    {
        $this->expectException(\RuntimeException::class);
        $session = $this->createMock(SessionInterface::class);
        $request = Request::create('/');
        $request->setSession($session);
        $controller = $this->controllerWithExtendingSession(...);

        self::getResolver()->getArguments($request, $controller);
    }

    public function testGetSessionMissMatchWithImplementation()
    {
        $this->expectException(\RuntimeException::class);
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $controller = $this->controllerWithExtendingSession(...);

        self::getResolver()->getArguments($request, $controller);
    }

    public function testGetSessionMissMatchOnNull()
    {
        $this->expectException(\RuntimeException::class);
        $request = Request::create('/');
        $controller = $this->controllerWithExtendingSession(...);

        self::getResolver()->getArguments($request, $controller);
    }

    public function testTargetedResolver()
    {
        $resolver = self::getResolver([], [DefaultValueResolver::class => new DefaultValueResolver()]);

        $request = Request::create('/');
        $request->attributes->set('foo', 'bar');
        $controller = $this->controllerTargetingResolver(...);

        $this->assertSame([1], $resolver->getArguments($request, $controller));
    }

    public function testDisabledResolver()
    {
        $resolver = self::getResolver(namedResolvers: []);

        $request = Request::create('/');
        $request->attributes->set('foo', 'bar');
        $controller = $this->controllerDisablingResolver(...);

        $this->assertSame([1], $resolver->getArguments($request, $controller));
    }

    public function testManyTargetedResolvers()
    {
        $resolver = self::getResolver(namedResolvers: []);

        $request = Request::create('/');
        $controller = $this->controllerTargetingManyResolvers(...);

        $this->expectException(\LogicException::class);
        $resolver->getArguments($request, $controller);
    }

    public function testUnknownTargetedResolver()
    {
        $resolver = self::getResolver(namedResolvers: []);

        $request = Request::create('/');
        $controller = $this->controllerTargetingUnknownResolver(...);

        $this->expectException(ResolverNotFoundException::class);
        $resolver->getArguments($request, $controller);
    }

    public function __invoke($foo, $bar = null)
    {
    }

    public function controllerWithFoo($foo)
    {
    }

    public function controllerWithoutArguments()
    {
    }

    public function controllerWithFooAndDefaultBar($foo, $bar = null)
    {
    }

    public function controllerWithFooBarFoobar($foo, $bar, $foobar)
    {
    }

    public function controllerWithRequest(Request $request)
    {
    }

    public function controllerWithExtendingRequest(ExtendingRequest $request)
    {
    }

    public function controllerWithSession(Session $session)
    {
    }

    public function controllerWithSessionInterface(SessionInterface $session)
    {
    }

    public function controllerWithExtendingSession(ExtendingSession $session)
    {
    }

    public function controllerTargetingResolver(#[ValueResolver(DefaultValueResolver::class)] int $foo = 1)
    {
    }

    public function controllerDisablingResolver(#[ValueResolver(RequestAttributeValueResolver::class, disabled: true)] int $foo = 1)
    {
    }

    public function controllerTargetingManyResolvers(
        #[ValueResolver(RequestAttributeValueResolver::class)]
        #[ValueResolver(DefaultValueResolver::class)]
        int $foo
    ) {
    }

    public function controllerTargetingUnknownResolver(
        #[ValueResolver('foo')]
        int $bar
    ) {
    }
}

function controller_function($foo, $foobar)
{
}
