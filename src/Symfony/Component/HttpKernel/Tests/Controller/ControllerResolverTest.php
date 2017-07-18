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
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpFoundation\Request;

class ControllerResolverTest extends TestCase
{
    public function testGetControllerWithoutControllerParameter()
    {
        $logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
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
        if (method_exists($this, 'expectException')) {
            $this->expectException($exceptionName);
            $this->expectExceptionMessage($exceptionMessage);
        } else {
            $this->setExpectedException($exceptionName, $exceptionMessage);
        }

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

    protected static function controllerMethod4()
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
