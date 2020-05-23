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

    public static function setUpBeforeClass()
    {
        $factory = new ArgumentMetadataFactory();

        self::$resolver = new ArgumentResolver($factory);
    }

    public function testDefaultState()
    {
        $this->assertEquals(self::$resolver, new ArgumentResolver());
        $this->assertNotEquals(self::$resolver, new ArgumentResolver(null, [new RequestAttributeValueResolver()]));
    }

    public function testGetArguments()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = [new self(), 'controllerWithFoo'];

        $this->assertEquals(['foo'], self::$resolver->getArguments($request, $controller), '->getArguments() returns an array of arguments for the controller method');
    }

    public function testGetArgumentsReturnsEmptyArrayWhenNoArguments()
    {
        $request = Request::create('/');
        $controller = [new self(), 'controllerWithoutArguments'];

        $this->assertEquals([], self::$resolver->getArguments($request, $controller), '->getArguments() returns an empty array if the method takes no arguments');
    }

    public function testGetArgumentsUsesDefaultValue()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = [new self(), 'controllerWithFooAndDefaultBar'];

        $this->assertEquals(['foo', null], self::$resolver->getArguments($request, $controller), '->getArguments() uses default values if present');
    }

    public function testGetArgumentsOverrideDefaultValueByRequestAttribute()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', 'bar');
        $controller = [new self(), 'controllerWithFooAndDefaultBar'];

        $this->assertEquals(['foo', 'bar'], self::$resolver->getArguments($request, $controller), '->getArguments() overrides default values if provided in the request attributes');
    }

    public function testGetArgumentsFromClosure()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = function ($foo) {};

        $this->assertEquals(['foo'], self::$resolver->getArguments($request, $controller));
    }

    public function testGetArgumentsUsesDefaultValueFromClosure()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = function ($foo, $bar = 'bar') {};

        $this->assertEquals(['foo', 'bar'], self::$resolver->getArguments($request, $controller));
    }

    public function testGetArgumentsFromInvokableObject()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = new self();

        $this->assertEquals(['foo', null], self::$resolver->getArguments($request, $controller));

        // Test default bar overridden by request attribute
        $request->attributes->set('bar', 'bar');

        $this->assertEquals(['foo', 'bar'], self::$resolver->getArguments($request, $controller));
    }

    public function testGetArgumentsFromFunctionName()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('foobar', 'foobar');
        $controller = __NAMESPACE__.'\controller_function';

        $this->assertEquals(['foo', 'foobar'], self::$resolver->getArguments($request, $controller));
    }

    public function testGetArgumentsFailsOnUnresolvedValue()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('foobar', 'foobar');
        $controller = [new self(), 'controllerWithFooBarFoobar'];

        try {
            self::$resolver->getArguments($request, $controller);
            $this->fail('->getArguments() throws a \RuntimeException exception if it cannot determine the argument value');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\RuntimeException', $e, '->getArguments() throws a \RuntimeException exception if it cannot determine the argument value');
        }
    }

    public function testGetArgumentsInjectsRequest()
    {
        $request = Request::create('/');
        $controller = [new self(), 'controllerWithRequest'];

        $this->assertEquals([$request], self::$resolver->getArguments($request, $controller), '->getArguments() injects the request');
    }

    public function testGetArgumentsInjectsExtendingRequest()
    {
        $request = ExtendingRequest::create('/');
        $controller = [new self(), 'controllerWithExtendingRequest'];

        $this->assertEquals([$request], self::$resolver->getArguments($request, $controller), '->getArguments() injects the request when extended');
    }

    /**
     * @requires PHP 5.6
     */
    public function testGetVariadicArguments()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', ['foo', 'bar']);
        $controller = [new VariadicController(), 'action'];

        $this->assertEquals(['foo', 'foo', 'bar'], self::$resolver->getArguments($request, $controller));
    }

    /**
     * @requires PHP 5.6
     */
    public function testGetVariadicArgumentsWithoutArrayInRequest()
    {
        $this->expectException('InvalidArgumentException');
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', 'foo');
        $controller = [new VariadicController(), 'action'];

        self::$resolver->getArguments($request, $controller);
    }

    /**
     * @requires PHP 5.6
     */
    public function testGetArgumentWithoutArray()
    {
        $this->expectException('InvalidArgumentException');
        $factory = new ArgumentMetadataFactory();
        $valueResolver = $this->getMockBuilder(ArgumentValueResolverInterface::class)->getMock();
        $resolver = new ArgumentResolver($factory, [$valueResolver]);

        $valueResolver->expects($this->any())->method('supports')->willReturn(true);
        $valueResolver->expects($this->any())->method('resolve')->willReturn('foo');

        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', 'foo');
        $controller = [$this, 'controllerWithFooAndDefaultBar'];
        $resolver->getArguments($request, $controller);
    }

    public function testIfExceptionIsThrownWhenMissingAnArgument()
    {
        $this->expectException('RuntimeException');
        $request = Request::create('/');
        $controller = [$this, 'controllerWithFoo'];

        self::$resolver->getArguments($request, $controller);
    }

    /**
     * @requires PHP 7.1
     */
    public function testGetNullableArguments()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', new \stdClass());
        $request->attributes->set('last', 'last');
        $controller = [new NullableController(), 'action'];

        $this->assertEquals(['foo', new \stdClass(), 'value', 'last'], self::$resolver->getArguments($request, $controller));
    }

    /**
     * @requires PHP 7.1
     */
    public function testGetNullableArgumentsWithDefaults()
    {
        $request = Request::create('/');
        $request->attributes->set('last', 'last');
        $controller = [new NullableController(), 'action'];

        $this->assertEquals([null, null, 'value', 'last'], self::$resolver->getArguments($request, $controller));
    }

    public function testGetSessionArguments()
    {
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $controller = [$this, 'controllerWithSession'];

        $this->assertEquals([$session], self::$resolver->getArguments($request, $controller));
    }

    public function testGetSessionArgumentsWithExtendedSession()
    {
        $session = new ExtendingSession(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $controller = [$this, 'controllerWithExtendingSession'];

        $this->assertEquals([$session], self::$resolver->getArguments($request, $controller));
    }

    public function testGetSessionArgumentsWithInterface()
    {
        $session = $this->getMockBuilder(SessionInterface::class)->getMock();
        $request = Request::create('/');
        $request->setSession($session);
        $controller = [$this, 'controllerWithSessionInterface'];

        $this->assertEquals([$session], self::$resolver->getArguments($request, $controller));
    }

    public function testGetSessionMissMatchWithInterface()
    {
        $this->expectException('RuntimeException');
        $session = $this->getMockBuilder(SessionInterface::class)->getMock();
        $request = Request::create('/');
        $request->setSession($session);
        $controller = [$this, 'controllerWithExtendingSession'];

        self::$resolver->getArguments($request, $controller);
    }

    public function testGetSessionMissMatchWithImplementation()
    {
        $this->expectException('RuntimeException');
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $controller = [$this, 'controllerWithExtendingSession'];

        self::$resolver->getArguments($request, $controller);
    }

    public function testGetSessionMissMatchOnNull()
    {
        $this->expectException('RuntimeException');
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

    protected function controllerWithFooAndDefaultBar($foo, $bar = null)
    {
    }

    protected function controllerWithFooBarFoobar($foo, $bar, $foobar)
    {
    }

    protected function controllerWithRequest(Request $request)
    {
    }

    protected function controllerWithExtendingRequest(ExtendingRequest $request)
    {
    }

    protected function controllerWithSession(Session $session)
    {
    }

    protected function controllerWithSessionInterface(SessionInterface $session)
    {
    }

    protected function controllerWithExtendingSession(ExtendingSession $session)
    {
    }
}

function controller_function($foo, $foobar)
{
}
