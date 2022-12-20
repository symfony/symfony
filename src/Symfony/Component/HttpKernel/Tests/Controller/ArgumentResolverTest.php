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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestAttributeValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadataFactory;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\ExtendingRequest;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\ExtendingSession;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\NullableController;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\VariadicController;

class ArgumentResolverTest extends TestCase
{
    /** @var ArgumentResolver */
    private static $resolver;

    public static function setUpBeforeClass(): void
    {
        $factory = new ArgumentMetadataFactory();

        self::$resolver = new ArgumentResolver($factory);
    }

    public function testDefaultState()
    {
        self::assertEquals(self::$resolver, new ArgumentResolver());
        self::assertNotEquals(self::$resolver, new ArgumentResolver(null, [new RequestAttributeValueResolver()]));
    }

    public function testGetArguments()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = [new self(), 'controllerWithFoo'];

        self::assertEquals(['foo'], self::$resolver->getArguments($request, $controller), '->getArguments() returns an array of arguments for the controller method');
    }

    public function testGetArgumentsReturnsEmptyArrayWhenNoArguments()
    {
        $request = Request::create('/');
        $controller = [new self(), 'controllerWithoutArguments'];

        self::assertEquals([], self::$resolver->getArguments($request, $controller), '->getArguments() returns an empty array if the method takes no arguments');
    }

    public function testGetArgumentsUsesDefaultValue()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = [new self(), 'controllerWithFooAndDefaultBar'];

        self::assertEquals(['foo', null], self::$resolver->getArguments($request, $controller), '->getArguments() uses default values if present');
    }

    public function testGetArgumentsOverrideDefaultValueByRequestAttribute()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', 'bar');
        $controller = [new self(), 'controllerWithFooAndDefaultBar'];

        self::assertEquals(['foo', 'bar'], self::$resolver->getArguments($request, $controller), '->getArguments() overrides default values if provided in the request attributes');
    }

    public function testGetArgumentsFromClosure()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = function ($foo) {};

        self::assertEquals(['foo'], self::$resolver->getArguments($request, $controller));
    }

    public function testGetArgumentsUsesDefaultValueFromClosure()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = function ($foo, $bar = 'bar') {};

        self::assertEquals(['foo', 'bar'], self::$resolver->getArguments($request, $controller));
    }

    public function testGetArgumentsFromInvokableObject()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = new self();

        self::assertEquals(['foo', null], self::$resolver->getArguments($request, $controller));

        // Test default bar overridden by request attribute
        $request->attributes->set('bar', 'bar');

        self::assertEquals(['foo', 'bar'], self::$resolver->getArguments($request, $controller));
    }

    public function testGetArgumentsFromFunctionName()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('foobar', 'foobar');
        $controller = __NAMESPACE__.'\controller_function';

        self::assertEquals(['foo', 'foobar'], self::$resolver->getArguments($request, $controller));
    }

    public function testGetArgumentsFailsOnUnresolvedValue()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('foobar', 'foobar');
        $controller = [new self(), 'controllerWithFooBarFoobar'];

        try {
            self::$resolver->getArguments($request, $controller);
            self::fail('->getArguments() throws a \RuntimeException exception if it cannot determine the argument value');
        } catch (\Exception $e) {
            self::assertInstanceOf(\RuntimeException::class, $e, '->getArguments() throws a \RuntimeException exception if it cannot determine the argument value');
        }
    }

    public function testGetArgumentsInjectsRequest()
    {
        $request = Request::create('/');
        $controller = [new self(), 'controllerWithRequest'];

        self::assertEquals([$request], self::$resolver->getArguments($request, $controller), '->getArguments() injects the request');
    }

    public function testGetArgumentsInjectsExtendingRequest()
    {
        $request = ExtendingRequest::create('/');
        $controller = [new self(), 'controllerWithExtendingRequest'];

        self::assertEquals([$request], self::$resolver->getArguments($request, $controller), '->getArguments() injects the request when extended');
    }

    public function testGetVariadicArguments()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', ['foo', 'bar']);
        $controller = [new VariadicController(), 'action'];

        self::assertEquals(['foo', 'foo', 'bar'], self::$resolver->getArguments($request, $controller));
    }

    public function testGetVariadicArgumentsWithoutArrayInRequest()
    {
        self::expectException(\InvalidArgumentException::class);
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', 'foo');
        $controller = [new VariadicController(), 'action'];

        self::$resolver->getArguments($request, $controller);
    }

    public function testGetArgumentWithoutArray()
    {
        self::expectException(\InvalidArgumentException::class);
        $factory = new ArgumentMetadataFactory();
        $valueResolver = self::createMock(ArgumentValueResolverInterface::class);
        $resolver = new ArgumentResolver($factory, [$valueResolver]);

        $valueResolver->expects(self::any())->method('supports')->willReturn(true);
        $valueResolver->expects(self::any())->method('resolve')->willReturn([]);

        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', 'foo');
        $controller = [$this, 'controllerWithFooAndDefaultBar'];
        $resolver->getArguments($request, $controller);
    }

    public function testIfExceptionIsThrownWhenMissingAnArgument()
    {
        self::expectException(\RuntimeException::class);
        $request = Request::create('/');
        $controller = [$this, 'controllerWithFoo'];

        self::$resolver->getArguments($request, $controller);
    }

    public function testGetNullableArguments()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', new \stdClass());
        $request->attributes->set('last', 'last');
        $controller = [new NullableController(), 'action'];

        self::assertEquals(['foo', new \stdClass(), 'value', 'last'], self::$resolver->getArguments($request, $controller));
    }

    public function testGetNullableArgumentsWithDefaults()
    {
        $request = Request::create('/');
        $request->attributes->set('last', 'last');
        $controller = [new NullableController(), 'action'];

        self::assertEquals([null, null, 'value', 'last'], self::$resolver->getArguments($request, $controller));
    }

    public function testGetSessionArguments()
    {
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $controller = [$this, 'controllerWithSession'];

        self::assertEquals([$session], self::$resolver->getArguments($request, $controller));
    }

    public function testGetSessionArgumentsWithExtendedSession()
    {
        $session = new ExtendingSession(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $controller = [$this, 'controllerWithExtendingSession'];

        self::assertEquals([$session], self::$resolver->getArguments($request, $controller));
    }

    public function testGetSessionArgumentsWithInterface()
    {
        $session = self::createMock(SessionInterface::class);
        $request = Request::create('/');
        $request->setSession($session);
        $controller = [$this, 'controllerWithSessionInterface'];

        self::assertEquals([$session], self::$resolver->getArguments($request, $controller));
    }

    public function testGetSessionMissMatchWithInterface()
    {
        self::expectException(\RuntimeException::class);
        $session = self::createMock(SessionInterface::class);
        $request = Request::create('/');
        $request->setSession($session);
        $controller = [$this, 'controllerWithExtendingSession'];

        self::$resolver->getArguments($request, $controller);
    }

    public function testGetSessionMissMatchWithImplementation()
    {
        self::expectException(\RuntimeException::class);
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $controller = [$this, 'controllerWithExtendingSession'];

        self::$resolver->getArguments($request, $controller);
    }

    public function testGetSessionMissMatchOnNull()
    {
        self::expectException(\RuntimeException::class);
        $request = Request::create('/');
        $controller = [$this, 'controllerWithExtendingSession'];

        self::$resolver->getArguments($request, $controller);
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
}

function controller_function($foo, $foobar)
{
}
