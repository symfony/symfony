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
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;

class ControllerResolverTest extends TestCase
{
    public function testGetControllerWithoutControllerParameter()
    {
        $logger = $this->createMock(LoggerInterface::class);
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
        $object = new InvokableController();

        $request = Request::create('/');
        $request->attributes->set('_controller', $object);
        $controller = $resolver->getController($request);
        $this->assertSame($object, $controller);
    }

    public function testGetControllerWithObjectAndMethod()
    {
        $resolver = $this->createControllerResolver();
        $object = new ControllerTest();

        $request = Request::create('/');
        $request->attributes->set('_controller', [$object, 'publicAction']);
        $controller = $resolver->getController($request);
        $this->assertSame([$object, 'publicAction'], $controller);
    }

    public function testGetControllerWithClassAndMethodAsArray()
    {
        $resolver = $this->createControllerResolver();

        $request = Request::create('/');
        $request->attributes->set('_controller', [ControllerTest::class, 'publicAction']);
        $controller = $resolver->getController($request);
        $this->assertInstanceOf(ControllerTest::class, $controller[0]);
        $this->assertSame('publicAction', $controller[1]);
    }

    public function testGetControllerWithClassAndMethodAsString()
    {
        $resolver = $this->createControllerResolver();

        $request = Request::create('/');
        $request->attributes->set('_controller', ControllerTest::class.'::publicAction');
        $controller = $resolver->getController($request);
        $this->assertInstanceOf(ControllerTest::class, $controller[0]);
        $this->assertSame('publicAction', $controller[1]);
    }

    public function testGetControllerWithInvokableClass()
    {
        $resolver = $this->createControllerResolver();

        $request = Request::create('/');
        $request->attributes->set('_controller', InvokableController::class);
        $controller = $resolver->getController($request);
        $this->assertInstanceOf(InvokableController::class, $controller);
    }

    public function testGetControllerOnObjectWithoutInvokeMethod()
    {
        $this->expectException(\InvalidArgumentException::class);
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

    public function testGetControllerWithClosure()
    {
        $resolver = $this->createControllerResolver();

        $closure = fn () => 'test';

        $request = Request::create('/');
        $request->attributes->set('_controller', $closure);
        $controller = $resolver->getController($request);
        $this->assertInstanceOf(\Closure::class, $controller);
        $this->assertSame('test', $controller());
    }

    /**
     * @dataProvider getStaticControllers
     */
    public function testGetControllerWithStaticController($staticController, $returnValue)
    {
        $resolver = $this->createControllerResolver();

        $request = Request::create('/');
        $request->attributes->set('_controller', $staticController);
        $controller = $resolver->getController($request);
        $this->assertSame($staticController, $controller);
        $this->assertSame($returnValue, $controller());
    }

    public static function getStaticControllers()
    {
        return [
            [TestAbstractController::class.'::staticAction', 'foo'],
            [[TestAbstractController::class, 'staticAction'], 'foo'],
            [PrivateConstructorController::class.'::staticAction', 'bar'],
            [[PrivateConstructorController::class, 'staticAction'], 'bar'],
        ];
    }

    /**
     * @dataProvider getUndefinedControllers
     */
    public function testGetControllerWithUndefinedController($controller, $exceptionName = null, $exceptionMessage = null)
    {
        $resolver = $this->createControllerResolver();
        $this->expectException($exceptionName);
        $this->expectExceptionMessage($exceptionMessage);

        $request = Request::create('/');
        $request->attributes->set('_controller', $controller);
        $resolver->getController($request);
    }

    public static function getUndefinedControllers()
    {
        $controller = new ControllerTest();

        return [
            ['foo', \Error::class, 'Class "foo" not found'],
            ['oof::bar', \Error::class, 'Class "oof" not found'],
            [['oof', 'bar'], \Error::class, 'Class "oof" not found'],
            ['Symfony\Component\HttpKernel\Tests\Controller\ControllerTest::staticsAction', \InvalidArgumentException::class, 'The controller for URI "/" is not callable: Expected method "staticsAction" on class "Symfony\Component\HttpKernel\Tests\Controller\ControllerTest", did you mean "staticAction"?'],
            ['Symfony\Component\HttpKernel\Tests\Controller\ControllerTest::privateAction', \InvalidArgumentException::class, 'The controller for URI "/" is not callable: Method "privateAction" on class "Symfony\Component\HttpKernel\Tests\Controller\ControllerTest" should be public and non-abstract'],
            ['Symfony\Component\HttpKernel\Tests\Controller\ControllerTest::protectedAction', \InvalidArgumentException::class, 'The controller for URI "/" is not callable: Method "protectedAction" on class "Symfony\Component\HttpKernel\Tests\Controller\ControllerTest" should be public and non-abstract'],
            ['Symfony\Component\HttpKernel\Tests\Controller\ControllerTest::undefinedAction', \InvalidArgumentException::class, 'The controller for URI "/" is not callable: Expected method "undefinedAction" on class "Symfony\Component\HttpKernel\Tests\Controller\ControllerTest". Available methods: "publicAction", "staticAction"'],
            ['Symfony\Component\HttpKernel\Tests\Controller\ControllerTest', \InvalidArgumentException::class, 'The controller for URI "/" is not callable: Controller class "Symfony\Component\HttpKernel\Tests\Controller\ControllerTest" cannot be called without a method name. You need to implement "__invoke" or use one of the available methods: "publicAction", "staticAction".'],
            [[$controller, 'staticsAction'], \InvalidArgumentException::class, 'The controller for URI "/" is not callable: Expected method "staticsAction" on class "Symfony\Component\HttpKernel\Tests\Controller\ControllerTest", did you mean "staticAction"?'],
            [[$controller, 'privateAction'], \InvalidArgumentException::class, 'The controller for URI "/" is not callable: Method "privateAction" on class "Symfony\Component\HttpKernel\Tests\Controller\ControllerTest" should be public and non-abstract'],
            [[$controller, 'protectedAction'], \InvalidArgumentException::class, 'The controller for URI "/" is not callable: Method "protectedAction" on class "Symfony\Component\HttpKernel\Tests\Controller\ControllerTest" should be public and non-abstract'],
            [[$controller, 'undefinedAction'], \InvalidArgumentException::class, 'The controller for URI "/" is not callable: Expected method "undefinedAction" on class "Symfony\Component\HttpKernel\Tests\Controller\ControllerTest". Available methods: "publicAction", "staticAction"'],
            [$controller, \InvalidArgumentException::class, 'The controller for URI "/" is not callable: Controller class "Symfony\Component\HttpKernel\Tests\Controller\ControllerTest" cannot be called without a method name. You need to implement "__invoke" or use one of the available methods: "publicAction", "staticAction".'],
            [['a' => 'foo', 'b' => 'bar'], \InvalidArgumentException::class, 'The controller for URI "/" is not callable: Invalid array callable, expected [controller, method].'],
        ];
    }

    public function testAllowedControllerTypes()
    {
        $resolver = $this->createControllerResolver();

        $request = Request::create('/');
        $controller = new ControllerTest();
        $request->attributes->set('_controller', [$controller, 'publicAction']);
        $request->attributes->set('_check_controller_is_allowed', true);

        try {
            $resolver->getController($request);
            $this->expectException(BadRequestException::class);
        } catch (BadRequestException) {
            // expected
        }

        $resolver->allowControllers(types: [ControllerTest::class]);

        $this->assertSame([$controller, 'publicAction'], $resolver->getController($request));

        $request->attributes->set('_controller', $action = $controller->publicAction(...));
        $this->assertSame($action, $resolver->getController($request));
    }

    public function testAllowedControllerAttributes()
    {
        $resolver = $this->createControllerResolver();

        $request = Request::create('/');
        $controller = some_controller_function(...);
        $request->attributes->set('_controller', $controller);
        $request->attributes->set('_check_controller_is_allowed', true);

        try {
            $resolver->getController($request);
            $this->expectException(BadRequestException::class);
        } catch (BadRequestException) {
            // expected
        }

        $resolver->allowControllers(attributes: [DummyController::class]);

        $this->assertSame($controller, $resolver->getController($request));

        $controller = some_controller_function::class;
        $request->attributes->set('_controller', $controller);
        $this->assertSame($controller, $resolver->getController($request));
    }

    public function testAllowedAsControllerAttribute()
    {
        $resolver = $this->createControllerResolver();

        $request = Request::create('/');
        $controller = new InvokableController();
        $request->attributes->set('_controller', [$controller, '__invoke']);
        $request->attributes->set('_check_controller_is_allowed', true);

        $this->assertSame([$controller, '__invoke'], $resolver->getController($request));

        $request->attributes->set('_controller', $controller);
        $this->assertSame($controller, $resolver->getController($request));
    }

    protected function createControllerResolver(?LoggerInterface $logger = null)
    {
        return new ControllerResolver($logger);
    }
}

#[DummyController]
function some_controller_function($foo, $foobar)
{
}

class ControllerTest
{
    public function __construct()
    {
    }

    public function __toString(): string
    {
        return '';
    }

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

#[AsController]
class InvokableController
{
    public function __invoke($foo, $bar = null)
    {
    }
}

abstract class TestAbstractController
{
    public static function staticAction()
    {
        return 'foo';
    }
}

class PrivateConstructorController
{
    private function __construct()
    {
    }

    public static function staticAction()
    {
        return 'bar';
    }
}
