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

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\NullableController;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\VariadicController;
use Symfony\Component\HttpFoundation\Request;

class ControllerResolverTest extends \PHPUnit_Framework_TestCase
{
    public function testGetControllerWithoutControllerParameter()
    {
        $logger = $this->getMock('Psr\Log\LoggerInterface');
        $logger->expects($this->once())->method('warning')->with('Unable to look for the controller as the "_controller" parameter is missing.');
        $resolver = $this->createControllerResolver($logger);

        $request = Request::create('/');
        $this->assertFalse($resolver->getController($request), '->getController() returns false when the request has no _controller attribute');
    }

    public function testGetControllerWithLambda()
    {
        $resolver = $this->createControllerResolver();

        $request = Request::create('/');
        $request->attributes->set('_controller', $lambda = function () {});
        $controller = $resolver->getController($request);
        $this->assertSame($lambda, $controller);
    }

    public function testGetControllerWithObjectAndInvokeMethod()
    {
        $resolver = $this->createControllerResolver();

        $request = Request::create('/');
        $request->attributes->set('_controller', $this);
        $controller = $resolver->getController($request);
        $this->assertSame($this, $controller);
    }

    public function testGetControllerWithObjectAndMethod()
    {
        $resolver = $this->createControllerResolver();

        $request = Request::create('/');
        $request->attributes->set('_controller', array($this, 'controllerMethod1'));
        $controller = $resolver->getController($request);
        $this->assertSame(array($this, 'controllerMethod1'), $controller);
    }

    public function testGetControllerWithClassAndMethod()
    {
        $resolver = $this->createControllerResolver();

        $request = Request::create('/');
        $request->attributes->set('_controller', array('Symfony\Component\HttpKernel\Tests\Controller\ControllerResolverTest', 'controllerMethod4'));
        $controller = $resolver->getController($request);
        $this->assertSame(array('Symfony\Component\HttpKernel\Tests\Controller\ControllerResolverTest', 'controllerMethod4'), $controller);
    }

    public function testGetControllerWithObjectAndMethodAsString()
    {
        $resolver = $this->createControllerResolver();

        $request = Request::create('/');
        $request->attributes->set('_controller', 'Symfony\Component\HttpKernel\Tests\Controller\ControllerResolverTest::controllerMethod1');
        $controller = $resolver->getController($request);
        $this->assertInstanceOf('Symfony\Component\HttpKernel\Tests\Controller\ControllerResolverTest', $controller[0], '->getController() returns a PHP callable');
    }

    public function testGetControllerWithClassAndInvokeMethod()
    {
        $resolver = $this->createControllerResolver();

        $request = Request::create('/');
        $request->attributes->set('_controller', 'Symfony\Component\HttpKernel\Tests\Controller\ControllerResolverTest');
        $controller = $resolver->getController($request);
        $this->assertInstanceOf('Symfony\Component\HttpKernel\Tests\Controller\ControllerResolverTest', $controller);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetControllerOnObjectWithoutInvokeMethod()
    {
        $resolver = $this->createControllerResolver();

        $request = Request::create('/');
        $request->attributes->set('_controller', new \stdClass());
        $resolver->getController($request);
    }

    public function testGetControllerWithFunction()
    {
        $resolver = $this->createControllerResolver();

        $request = Request::create('/');
        $request->attributes->set('_controller', 'Symfony\Component\HttpKernel\Tests\Controller\some_controller_function');
        $controller = $resolver->getController($request);
        $this->assertSame('Symfony\Component\HttpKernel\Tests\Controller\some_controller_function', $controller);
    }

    /**
     * @dataProvider getUndefinedControllers
     */
    public function testGetControllerOnNonUndefinedFunction($controller, $exceptionName = null, $exceptionMessage = null)
    {
        $resolver = $this->createControllerResolver();
        $this->setExpectedException($exceptionName, $exceptionMessage);

        $request = Request::create('/');
        $request->attributes->set('_controller', $controller);
        $resolver->getController($request);
    }

    public function getUndefinedControllers()
    {
        return array(
            array(1, 'InvalidArgumentException', 'Unable to find controller "1".'),
            array('foo', 'InvalidArgumentException', 'Unable to find controller "foo".'),
            array('oof::bar', 'InvalidArgumentException', 'Class "oof" does not exist.'),
            array('stdClass', 'InvalidArgumentException', 'Unable to find controller "stdClass".'),
            array('Symfony\Component\HttpKernel\Tests\Controller\ControllerTest::staticsAction', 'InvalidArgumentException', 'The controller for URI "/" is not callable. Expected method "staticsAction" on class "Symfony\Component\HttpKernel\Tests\Controller\ControllerTest", did you mean "staticAction"?'),
            array('Symfony\Component\HttpKernel\Tests\Controller\ControllerTest::privateAction', 'InvalidArgumentException', 'The controller for URI "/" is not callable. Method "privateAction" on class "Symfony\Component\HttpKernel\Tests\Controller\ControllerTest" should be public and non-abstract'),
            array('Symfony\Component\HttpKernel\Tests\Controller\ControllerTest::protectedAction', 'InvalidArgumentException', 'The controller for URI "/" is not callable. Method "protectedAction" on class "Symfony\Component\HttpKernel\Tests\Controller\ControllerTest" should be public and non-abstract'),
            array('Symfony\Component\HttpKernel\Tests\Controller\ControllerTest::undefinedAction', 'InvalidArgumentException', 'The controller for URI "/" is not callable. Expected method "undefinedAction" on class "Symfony\Component\HttpKernel\Tests\Controller\ControllerTest". Available methods: "publicAction", "staticAction"'),
        );
    }

    /**
     * @group legacy
     */
    public function testGetArguments()
    {
        $resolver = $this->createControllerResolver();

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
        $controller = 'Symfony\Component\HttpKernel\Tests\Controller\some_controller_function';
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
        $mock->expects($this->once())->method('createController')->will($this->returnValue('Symfony\Component\HttpKernel\Tests\Controller\some_controller_function'));

        $request = Request::create('/');
        $request->attributes->set('_controller', 'foobar');
        $mock->getController($request);
    }

    /**
     * @requires PHP 7.1
     */
    public function testGetNullableArguments()
    {
        $resolver = new ControllerResolver();

        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', new \stdClass());
        $request->attributes->set('mandatory', 'mandatory');
        $controller = array(new NullableController(), 'action');
        $this->assertEquals(array('foo', new \stdClass(), 'value', 'mandatory'), $resolver->getArguments($request, $controller));
    }

    /**
     * @requires PHP 7.1
     */
    public function testGetNullableArgumentsWithDefaults()
    {
        $resolver = new ControllerResolver();

        $request = Request::create('/');
        $request->attributes->set('mandatory', 'mandatory');
        $controller = array(new NullableController(), 'action');
        $this->assertEquals(array(null, null, 'value', 'mandatory'), $resolver->getArguments($request, $controller));
    }

    protected function createControllerResolver(LoggerInterface $logger = null)
    {
        return new ControllerResolver($logger);
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

function some_controller_function($foo, $foobar)
{
}

class ControllerTest
{
    public function publicAction()
    {
    }

    private function privateAction()
    {
    }

    protected function protectedAction()
    {
    }

    public static function staticAction()
    {
    }
}
