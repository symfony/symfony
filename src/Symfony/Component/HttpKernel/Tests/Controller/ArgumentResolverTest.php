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

use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\VariadicController;
use Symfony\Component\HttpFoundation\Request;

class ArgumentResolverTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @group legacy
     */
    public function testGetArguments()
    {
        $resolver = new ArgumentResolver();

        $request = Request::create('/');
        $controller = array(new self(), 'testGetArguments');
        $this->assertEquals(array(), $resolver->getArguments($request, $controller), '->getArguments() returns an empty array if the method takes no arguments');

        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = array(new self(), 'controllerMethod1');
        $this->assertEquals(array('foo'), $resolver->getArguments($request, $controller), '->getArguments() returns an array of arguments for the controller method');

        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = array(new self(), 'controllerMethod2');
        $this->assertEquals(array('foo', null), $resolver->getArguments($request, $controller), '->getArguments() uses default values if present');

        $request->attributes->set('bar', 'bar');
        $this->assertEquals(array('foo', 'bar'), $resolver->getArguments($request, $controller), '->getArguments() overrides default values if provided in the request attributes');

        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = function ($foo) {};
        $this->assertEquals(array('foo'), $resolver->getArguments($request, $controller));

        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = function ($foo, $bar = 'bar') {};
        $this->assertEquals(array('foo', 'bar'), $resolver->getArguments($request, $controller));

        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = new self();
        $this->assertEquals(array('foo', null), $resolver->getArguments($request, $controller));
        $request->attributes->set('bar', 'bar');
        $this->assertEquals(array('foo', 'bar'), $resolver->getArguments($request, $controller));

        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('foobar', 'foobar');
        $controller = 'Symfony\Component\HttpKernel\Tests\Controller\another_controller_function';
        $this->assertEquals(array('foo', 'foobar'), $resolver->getArguments($request, $controller));

        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('foobar', 'foobar');
        $controller = array(new self(), 'controllerMethod3');

        try {
            $resolver->getArguments($request, $controller);
            $this->fail('->getArguments() throws a \RuntimeException exception if it cannot determine the argument value');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\RuntimeException', $e, '->getArguments() throws a \RuntimeException exception if it cannot determine the argument value');
        }

        $request = Request::create('/');
        $controller = array(new self(), 'controllerMethod5');
        $this->assertEquals(array($request), $resolver->getArguments($request, $controller), '->getArguments() injects the request');
    }

    /**
     * @requires PHP 5.6
     * @group legacy
     */
    public function testGetVariadicArguments()
    {
        $resolver = new ControllerResolver();

        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', array('foo', 'bar'));
        $controller = array(new VariadicController(), 'action');
        $this->assertEquals(array('foo', 'foo', 'bar'), $resolver->getArguments($request, $controller));
    }

    public function testCreateControllerCanReturnAnyCallable()
    {
        $mock = $this->getMock('Symfony\Component\HttpKernel\Controller\ControllerResolver', array('createController'));
        $mock->expects($this->once())->method('createController')->will($this->returnValue('Symfony\Component\HttpKernel\Tests\Controller\another_controller_function'));

        $request = Request::create('/');
        $request->attributes->set('_controller', 'foobar');
        $mock->getController($request);
    }

    public function __invoke($foo, $bar = null)
    {
    }

    public function controllerMethod1($foo)
    {
    }

    protected function controllerMethod2($foo, $bar = null)
    {
    }

    protected function controllerMethod3($foo, $bar, $foobar)
    {
    }

    protected static function controllerMethod4()
    {
    }

    protected function controllerMethod5(Request $request)
    {
    }
}

function another_controller_function($foo, $foobar)
{
}
