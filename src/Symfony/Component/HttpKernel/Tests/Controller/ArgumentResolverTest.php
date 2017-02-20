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
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\DefaultValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestAttributeValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\VariadicValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadataFactory;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\ExtendingRequest;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\NullableController;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\VariadicController;
use Symfony\Component\HttpFoundation\Request;

class ArgumentResolverTest extends TestCase
{
    /** @var ArgumentResolver */
    private static $resolver;

    public static function setUpBeforeClass()
    {
        $factory = new ArgumentMetadataFactory();
        $argumentValueResolvers = array(
            new RequestAttributeValueResolver(),
            new RequestValueResolver(),
            new DefaultValueResolver(),
            new VariadicValueResolver(),
        );

        self::$resolver = new ArgumentResolver($factory, $argumentValueResolvers);
    }

    public function testDefaultState()
    {
        $this->assertEquals(self::$resolver, new ArgumentResolver());
        $this->assertNotEquals(self::$resolver, new ArgumentResolver(null, array(new RequestAttributeValueResolver())));
    }

    public function testGetArguments()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = array(new self(), 'controllerWithFoo');

        $this->assertEquals(array('foo'), self::$resolver->getArguments($request, $controller), '->getArguments() returns an array of arguments for the controller method');
    }

    public function testGetArgumentsReturnsEmptyArrayWhenNoArguments()
    {
        $request = Request::create('/');
        $controller = array(new self(), 'controllerWithoutArguments');

        $this->assertEquals(array(), self::$resolver->getArguments($request, $controller), '->getArguments() returns an empty array if the method takes no arguments');
    }

    public function testGetArgumentsUsesDefaultValue()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = array(new self(), 'controllerWithFooAndDefaultBar');

        $this->assertEquals(array('foo', null), self::$resolver->getArguments($request, $controller), '->getArguments() uses default values if present');
    }

    public function testGetArgumentsOverrideDefaultValueByRequestAttribute()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', 'bar');
        $controller = array(new self(), 'controllerWithFooAndDefaultBar');

        $this->assertEquals(array('foo', 'bar'), self::$resolver->getArguments($request, $controller), '->getArguments() overrides default values if provided in the request attributes');
    }

    public function testGetArgumentsFromClosure()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = function ($foo) {};

        $this->assertEquals(array('foo'), self::$resolver->getArguments($request, $controller));
    }

    public function testGetArgumentsUsesDefaultValueFromClosure()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = function ($foo, $bar = 'bar') {};

        $this->assertEquals(array('foo', 'bar'), self::$resolver->getArguments($request, $controller));
    }

    public function testGetArgumentsFromInvokableObject()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = new self();

        $this->assertEquals(array('foo', null), self::$resolver->getArguments($request, $controller));

        // Test default bar overridden by request attribute
        $request->attributes->set('bar', 'bar');

        $this->assertEquals(array('foo', 'bar'), self::$resolver->getArguments($request, $controller));
    }

    public function testGetArgumentsFromFunctionName()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('foobar', 'foobar');
        $controller = __NAMESPACE__.'\controller_function';

        $this->assertEquals(array('foo', 'foobar'), self::$resolver->getArguments($request, $controller));
    }

    public function testGetArgumentsFailsOnUnresolvedValue()
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

    public function testGetArgumentsInjectsRequest()
    {
        $request = Request::create('/');
        $controller = array(new self(), 'controllerWithRequest');

        $this->assertEquals(array($request), self::$resolver->getArguments($request, $controller), '->getArguments() injects the request');
    }

    public function testGetArgumentsInjectsExtendingRequest()
    {
        $request = ExtendingRequest::create('/');
        $controller = array(new self(), 'controllerWithExtendingRequest');

        $this->assertEquals(array($request), self::$resolver->getArguments($request, $controller), '->getArguments() injects the request when extended');
    }

    /**
     * @requires PHP 5.6
     */
    public function testGetVariadicArguments()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', array('foo', 'bar'));
        $controller = array(new VariadicController(), 'action');

        $this->assertEquals(array('foo', 'foo', 'bar'), self::$resolver->getArguments($request, $controller));
    }

    /**
     * @requires PHP 5.6
     * @expectedException \InvalidArgumentException
     */
    public function testGetVariadicArgumentsWithoutArrayInRequest()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', 'foo');
        $controller = array(new VariadicController(), 'action');

        self::$resolver->getArguments($request, $controller);
    }

    /**
     * @requires PHP 5.6
     * @expectedException \InvalidArgumentException
     */
    public function testGetArgumentWithoutArray()
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
    public function testIfExceptionIsThrownWhenMissingAnArgument()
    {
        $request = Request::create('/');
        $controller = array($this, 'controllerWithFoo');

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
        $request->attributes->set('mandatory', 'mandatory');
        $controller = array(new NullableController(), 'action');

        $this->assertEquals(array('foo', new \stdClass(), 'value', 'mandatory'), self::$resolver->getArguments($request, $controller));
    }

    /**
     * @requires PHP 7.1
     */
    public function testGetNullableArgumentsWithDefaults()
    {
        $request = Request::create('/');
        $request->attributes->set('mandatory', 'mandatory');
        $controller = array(new NullableController(), 'action');

        $this->assertEquals(array(null, null, 'value', 'mandatory'), self::$resolver->getArguments($request, $controller));
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
}

function controller_function($foo, $foobar)
{
}
