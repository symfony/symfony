<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpKernel;

use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpFoundation\Request;

require_once __DIR__.'/../Logger.php';

class ControllerResolverTest extends \PHPUnit_Framework_TestCase
{
    protected static $logger;
    protected static $resolver;

    public static function setUpBeforeClass()
    {
        self::$logger = new Logger();
        self::$resolver= new ControllerResolver(self::$logger);
    }

    public static function tearDownAfterClass()
    {
        self::$logger = null;
        self::$resolver = null;
    }

    public function test_controllerAttributeRequired()
    {
        $request = Request::create('/');
        $this->assertFalse(self::$resolver->getController($request), '->getController() returns false when the request has no _controller attribute');
        $this->assertEquals(array('Unable to look for the controller as the "_controller" parameter is missing'), self::$logger->getLogs('warn'));
    }

    public function testGetControllerReturnsACallable()
    {
        $request = Request::create('/');
        $request->attributes->set('_controller', 'Symfony\Tests\Component\HttpKernel\Controller::testAction');
        $controller = self::$resolver->getController($request);
        $this->assertInstanceOf('Symfony\Tests\Component\HttpKernel\Controller', $controller[0], '->getController() returns a PHP callable');
        $this->assertSame('testAction', $controller[1]);
    }

    public function testClosureAsController()
    {
        $request = Request::create('/');
        $request->attributes->set('_controller', $closure = function () {});
        $controller = self::$resolver->getController($request);
        $this->assertSame($closure, $controller);
    }

    public function testClassInstanceAsController()
    {
        $request = Request::create('/');
        $controller = new Controller();
        $request->attributes->set('_controller', $controller);
        $resolvedController = self::$resolver->getController($request);
        $this->assertSame($controller, $resolvedController);
    }

    public function testClassNameAsController()
    {
        $request = Request::create('/');
        $request->attributes->set('_controller', 'Symfony\Tests\Component\HttpKernel\Controller');
        $controller = self::$resolver->getController($request);
        $this->assertInstanceOf('Symfony\Tests\Component\HttpKernel\Controller', $controller);
    }

    public function testClassInstanceMethodNameAsController()
    {
        $controller = new Controller();
        $request = Request::create('/');
        $request->attributes->set('_controller', array($controller, 'controllerMethod1'));
        $resolvedController = self::$resolver->getController($request);
        $this->assertSame(array($controller, 'controllerMethod1'), $resolvedController);
    }

    public function testClassNameMethodNameAsController()
    {
        $request = Request::create('/');
        $request->attributes->set('_controller', array('Symfony\Tests\Component\HttpKernel\Controller', 'controllerMethod4'));
        $controller = self::$resolver->getController($request);
        $this->assertSame(array('Symfony\Tests\Component\HttpKernel\Controller', 'controllerMethod4'), $controller);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidControllerClass()
    {
        $request = Request::create('/');
        $request->attributes->set('_controller', 'foo');
        self::$resolver->getController($request);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidControllerClassAndMethod()
    {
        $request = Request::create('/');
        $request->attributes->set('_controller', 'foo::bar');
        self::$resolver->getController($request);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidControllerMethod()
    {
        $request = Request::create('/');
        $request->attributes->set('_controller', 'Symfony\Tests\Component\HttpKernel\Controller::bar');
        self::$resolver->getController($request);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testActionMustBePublic()
    {
        $request = Request::create('/');
        $request->attributes->set('_controller', 'Symfony\Tests\Component\HttpKernel\Controller::protectedAction');
        self::$resolver->getController($request);
    }

    public function testNoArguments()
    {
        $request = Request::create('/');
        $controller = array(new Controller(), 'testAction');
        $this->assertEquals(array(), self::$resolver->getArguments($request, $controller), '->getArguments() returns an empty array if the method takes no arguments');
    }

    public function testSingleArgument()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = array(new Controller(), 'controllerMethod1');
        $this->assertEquals(array('foo'), self::$resolver->getArguments($request, $controller), '->getArguments() returns an array of arguments for the controller method');
    }

    public function testTwoArgumentsWithDefault()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = array(new Controller(), 'controllerMethod2');
        $this->assertEquals(array('foo', null), self::$resolver->getArguments($request, $controller), '->getArguments() uses default values if present');
    }

    public function testTwoArgumentsAndOverrideDefaults()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', 'bar');
        $controller = array(new Controller(), 'controllerMethod2');
        $this->assertEquals(array('foo', 'bar'), self::$resolver->getArguments($request, $controller), '->getArguments() overrides default values if provided in the request attributes');
    }

    public function testClosureSingleArgument()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = function ($foo) {};
        $this->assertEquals(array('foo'), self::$resolver->getArguments($request, $controller));
    }

    public function testClosureTwoArgumentsWithDefaults()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = function ($foo, $bar = 'bar') {};
        $this->assertEquals(array('foo', 'bar'), self::$resolver->getArguments($request, $controller));
    }

    public function test__invokeArgumentsWithDefaults()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = new Controller();
        $this->assertEquals(array('foo', null), self::$resolver->getArguments($request, $controller));
    }

    public function test__invokeArgumentsOverrideDefaults()
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = new Controller();
        $request->attributes->set('bar', 'bar');
        $this->assertEquals(array('foo', 'bar'), self::$resolver->getArguments($request, $controller));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testThreeArguments()
    {
        if (version_compare(PHP_VERSION, '5.3.16', '==')) {
            $this->markTestSkipped('PHP 5.3.16 has a major bug in the Reflection sub-system');
        }
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('foobar', 'foobar');
        $controller = array(new Controller(), 'controllerMethod3');
        self::$resolver->getArguments($request, $controller);
    }

    public function testInjectRequest()
    {
        $request = Request::create('/');
        $controller = array(new Controller(), 'controllerMethod5');
        $this->assertEquals(array($request), self::$resolver->getArguments($request, $controller), '->getArguments() injects the request');
    }
}

class Controller
{
    public function __invoke($foo, $bar = null)
    {
    }

    public function testAction()
    {
    }

    protected function protectedAction()
    {
    }

    public function controllerMethod1($foo)
    {
    }

    public function controllerMethod2($foo, $bar = null)
    {
    }

    public function controllerMethod3($foo, $bar = null, $foobar)
    {
    }

    public static function controllerMethod4()
    {
    }

    public function controllerMethod5(Request $request)
    {
    }
}
