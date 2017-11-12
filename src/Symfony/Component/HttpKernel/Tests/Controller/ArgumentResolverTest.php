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
use Symfony\Component\HttpFoundation\Request;

class ArgumentResolverTest extends TestCase
{
    /** @var ArgumentResolver */
    private static $resolver;

    public static function setUpBeforeClass(): void
    {
        $factory = new ArgumentMetadataFactory();

        self::$resolver = new ArgumentResolver($factory);
    }

    public function testDefaultState(): void
    {
        $this->assertEquals(self::$resolver, new ArgumentResolver());
        $this->assertNotEquals(self::$resolver, new ArgumentResolver(null, array(new RequestAttributeValueResolver())));
    }

    public function testGetArguments(): void
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = array(new self(), 'controllerWithFoo');

        $this->assertEquals(array('foo'), self::$resolver->getArguments($request, $controller), '->getArguments() returns an array of arguments for the controller method');
    }

    public function testGetArgumentsReturnsEmptyArrayWhenNoArguments(): void
    {
        $request = Request::create('/');
        $controller = array(new self(), 'controllerWithoutArguments');

        $this->assertEquals(array(), self::$resolver->getArguments($request, $controller), '->getArguments() returns an empty array if the method takes no arguments');
    }

    public function testGetArgumentsUsesDefaultValue(): void
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = array(new self(), 'controllerWithFooAndDefaultBar');

        $this->assertEquals(array('foo', null), self::$resolver->getArguments($request, $controller), '->getArguments() uses default values if present');
    }

    public function testGetArgumentsOverrideDefaultValueByRequestAttribute(): void
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', 'bar');
        $controller = array(new self(), 'controllerWithFooAndDefaultBar');

        $this->assertEquals(array('foo', 'bar'), self::$resolver->getArguments($request, $controller), '->getArguments() overrides default values if provided in the request attributes');
    }

    public function testGetArgumentsFromClosure(): void
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = function ($foo): void {};

        $this->assertEquals(array('foo'), self::$resolver->getArguments($request, $controller));
    }

    public function testGetArgumentsUsesDefaultValueFromClosure(): void
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = function ($foo, $bar = 'bar'): void {};

        $this->assertEquals(array('foo', 'bar'), self::$resolver->getArguments($request, $controller));
    }

    public function testGetArgumentsFromInvokableObject(): void
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = new self();

        $this->assertEquals(array('foo', null), self::$resolver->getArguments($request, $controller));

        // Test default bar overridden by request attribute
        $request->attributes->set('bar', 'bar');

        $this->assertEquals(array('foo', 'bar'), self::$resolver->getArguments($request, $controller));
    }

    public function testGetArgumentsFromFunctionName(): void
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('foobar', 'foobar');
        $controller = __NAMESPACE__.'\controller_function';

        $this->assertEquals(array('foo', 'foobar'), self::$resolver->getArguments($request, $controller));
    }

    public function testGetArgumentsFailsOnUnresolvedValue(): void
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('foobar', 'foobar');
        $controller = array(new self(), 'controllerWithFooBarFoobar');

        try {
            self::$resolver->getArguments($request, $controller);
            $this->fail('->getArguments() throws a \RuntimeException exception if it cannot determine the argument value');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\RuntimeException', $e, '->getArguments() throws a \RuntimeException exception if it cannot determine the argument value');
        }
    }

    public function testGetArgumentsInjectsRequest(): void
    {
        $request = Request::create('/');
        $controller = array(new self(), 'controllerWithRequest');

        $this->assertEquals(array($request), self::$resolver->getArguments($request, $controller), '->getArguments() injects the request');
    }

    public function testGetArgumentsInjectsExtendingRequest(): void
    {
        $request = ExtendingRequest::create('/');
        $controller = array(new self(), 'controllerWithExtendingRequest');

        $this->assertEquals(array($request), self::$resolver->getArguments($request, $controller), '->getArguments() injects the request when extended');
    }

    public function testGetVariadicArguments(): void
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', array('foo', 'bar'));
        $controller = array(new VariadicController(), 'action');

        $this->assertEquals(array('foo', 'foo', 'bar'), self::$resolver->getArguments($request, $controller));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetVariadicArgumentsWithoutArrayInRequest(): void
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', 'foo');
        $controller = array(new VariadicController(), 'action');

        self::$resolver->getArguments($request, $controller);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetArgumentWithoutArray(): void
    {
        $factory = new ArgumentMetadataFactory();
        $valueResolver = $this->getMockBuilder(ArgumentValueResolverInterface::class)->getMock();
        $resolver = new ArgumentResolver($factory, array($valueResolver));

        $valueResolver->expects($this->any())->method('supports')->willReturn(true);
        $valueResolver->expects($this->any())->method('resolve')->willReturn('foo');

        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', 'foo');
        $controller = array($this, 'controllerWithFooAndDefaultBar');
        $resolver->getArguments($request, $controller);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testIfExceptionIsThrownWhenMissingAnArgument(): void
    {
        $request = Request::create('/');
        $controller = array($this, 'controllerWithFoo');

        self::$resolver->getArguments($request, $controller);
    }

    public function testGetNullableArguments(): void
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', new \stdClass());
        $request->attributes->set('mandatory', 'mandatory');
        $controller = array(new NullableController(), 'action');

        $this->assertEquals(array('foo', new \stdClass(), 'value', 'mandatory'), self::$resolver->getArguments($request, $controller));
    }

    public function testGetNullableArgumentsWithDefaults(): void
    {
        $request = Request::create('/');
        $request->attributes->set('mandatory', 'mandatory');
        $controller = array(new NullableController(), 'action');

        $this->assertEquals(array(null, null, 'value', 'mandatory'), self::$resolver->getArguments($request, $controller));
    }

    public function testGetSessionArguments(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $controller = array($this, 'controllerWithSession');

        $this->assertEquals(array($session), self::$resolver->getArguments($request, $controller));
    }

    public function testGetSessionArgumentsWithExtendedSession(): void
    {
        $session = new ExtendingSession(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $controller = array($this, 'controllerWithExtendingSession');

        $this->assertEquals(array($session), self::$resolver->getArguments($request, $controller));
    }

    public function testGetSessionArgumentsWithInterface(): void
    {
        $session = $this->getMockBuilder(SessionInterface::class)->getMock();
        $request = Request::create('/');
        $request->setSession($session);
        $controller = array($this, 'controllerWithSessionInterface');

        $this->assertEquals(array($session), self::$resolver->getArguments($request, $controller));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testGetSessionMissMatchWithInterface(): void
    {
        $session = $this->getMockBuilder(SessionInterface::class)->getMock();
        $request = Request::create('/');
        $request->setSession($session);
        $controller = array($this, 'controllerWithExtendingSession');

        self::$resolver->getArguments($request, $controller);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testGetSessionMissMatchWithImplementation(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $controller = array($this, 'controllerWithExtendingSession');

        self::$resolver->getArguments($request, $controller);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testGetSessionMissMatchOnNull(): void
    {
        $request = Request::create('/');
        $controller = array($this, 'controllerWithExtendingSession');

        self::$resolver->getArguments($request, $controller);
    }

    public function __invoke($foo, $bar = null): void
    {
    }

    public function controllerWithFoo($foo): void
    {
    }

    public function controllerWithoutArguments(): void
    {
    }

    protected function controllerWithFooAndDefaultBar($foo, $bar = null): void
    {
    }

    protected function controllerWithFooBarFoobar($foo, $bar, $foobar): void
    {
    }

    protected function controllerWithRequest(Request $request): void
    {
    }

    protected function controllerWithExtendingRequest(ExtendingRequest $request): void
    {
    }

    protected function controllerWithSession(Session $session): void
    {
    }

    protected function controllerWithSessionInterface(SessionInterface $session): void
    {
    }

    protected function controllerWithExtendingSession(ExtendingSession $session): void
    {
    }
}

function controller_function($foo, $foobar): void
{
}
