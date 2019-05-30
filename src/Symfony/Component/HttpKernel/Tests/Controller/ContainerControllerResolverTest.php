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

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Debug\ErrorHandler;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ContainerControllerResolver;

class ContainerControllerResolverTest extends ControllerResolverTest
{
    public function testGetControllerService()
    {
        $container = $this->createMockContainer();
        $container->expects($this->once())
            ->method('has')
            ->with('foo')
            ->willReturn(true);
        $container->expects($this->once())
            ->method('get')
            ->with('foo')
            ->willReturn($this)
        ;

        $resolver = $this->createControllerResolver(null, $container);
        $request = Request::create('/');
        $request->attributes->set('_controller', 'foo:controllerMethod1');

        $controller = $resolver->getController($request);

        $this->assertInstanceOf(\get_class($this), $controller[0]);
        $this->assertSame('controllerMethod1', $controller[1]);
    }

    public function testGetControllerInvokableService()
    {
        $invokableController = new InvokableController('bar');

        $container = $this->createMockContainer();
        $container->expects($this->once())
            ->method('has')
            ->with('foo')
            ->willReturn(true)
        ;
        $container->expects($this->once())
            ->method('get')
            ->with('foo')
            ->willReturn($invokableController)
        ;

        $resolver = $this->createControllerResolver(null, $container);
        $request = Request::create('/');
        $request->attributes->set('_controller', 'foo');

        $controller = $resolver->getController($request);

        $this->assertEquals($invokableController, $controller);
    }

    public function testGetControllerInvokableServiceWithClassNameAsName()
    {
        $invokableController = new InvokableController('bar');
        $className = __NAMESPACE__.'\InvokableController';

        $container = $this->createMockContainer();
        $container->expects($this->once())
            ->method('has')
            ->with($className)
            ->willReturn(true)
        ;
        $container->expects($this->once())
            ->method('get')
            ->with($className)
            ->willReturn($invokableController)
        ;

        $resolver = $this->createControllerResolver(null, $container);
        $request = Request::create('/');
        $request->attributes->set('_controller', $className);

        $controller = $resolver->getController($request);

        $this->assertEquals($invokableController, $controller);
    }

    public function testNonInstantiableController()
    {
        $container = $this->createMockContainer();
        $container->expects($this->once())
            ->method('has')
            ->with(NonInstantiableController::class)
            ->willReturn(false)
        ;

        $resolver = $this->createControllerResolver(null, $container);
        $request = Request::create('/');
        $request->attributes->set('_controller', [NonInstantiableController::class, 'action']);

        $controller = $resolver->getController($request);

        $this->assertSame([NonInstantiableController::class, 'action'], $controller);
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage Controller "Symfony\Component\HttpKernel\Tests\Controller\ImpossibleConstructController" cannot be fetched from the container because it is private. Did you forget to tag the service with "controller.service_arguments"?
     */
    public function testNonConstructController()
    {
        $container = $this->getMockBuilder(Container::class)->getMock();
        $container->expects($this->at(0))
            ->method('has')
            ->with(ImpossibleConstructController::class)
            ->willReturn(true)
        ;

        $container->expects($this->at(1))
            ->method('has')
            ->with(ImpossibleConstructController::class)
            ->willReturn(false)
        ;

        $container->expects($this->atLeastOnce())
            ->method('getRemovedIds')
            ->with()
            ->willReturn([ImpossibleConstructController::class => true])
        ;

        $resolver = $this->createControllerResolver(null, $container);
        $request = Request::create('/');
        $request->attributes->set('_controller', [ImpossibleConstructController::class, 'action']);

        if (\PHP_VERSION_ID < 70100) {
            ErrorHandler::register();
            try {
                $resolver->getController($request);
            } finally {
                restore_error_handler();
                restore_exception_handler();
            }
        } else {
            $resolver->getController($request);
        }
    }

    public function testNonInstantiableControllerWithCorrespondingService()
    {
        $service = new \stdClass();

        $container = $this->createMockContainer();
        $container->expects($this->atLeastOnce())
            ->method('has')
            ->with(NonInstantiableController::class)
            ->willReturn(true)
        ;
        $container->expects($this->atLeastOnce())
            ->method('get')
            ->with(NonInstantiableController::class)
            ->willReturn($service)
        ;

        $resolver = $this->createControllerResolver(null, $container);
        $request = Request::create('/');
        $request->attributes->set('_controller', [NonInstantiableController::class, 'action']);

        $controller = $resolver->getController($request);

        $this->assertSame([$service, 'action'], $controller);
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage Controller "app.my_controller" cannot be fetched from the container because it is private. Did you forget to tag the service with "controller.service_arguments"?
     */
    public function testExceptionWhenUsingRemovedControllerService()
    {
        $container = $this->getMockBuilder(Container::class)->getMock();
        $container->expects($this->at(0))
            ->method('has')
            ->with('app.my_controller')
            ->willReturn(false)
        ;

        $container->expects($this->atLeastOnce())
            ->method('getRemovedIds')
            ->with()
            ->willReturn(['app.my_controller' => true])
        ;

        $resolver = $this->createControllerResolver(null, $container);

        $request = Request::create('/');
        $request->attributes->set('_controller', 'app.my_controller');
        $resolver->getController($request);
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage Controller "app.my_controller" cannot be called without a method name. Did you forget an "__invoke" method?
     */
    public function testExceptionWhenUsingControllerWithoutAnInvokeMethod()
    {
        $container = $this->getMockBuilder(Container::class)->getMock();
        $container->expects($this->once())
            ->method('has')
            ->with('app.my_controller')
            ->willReturn(true)
        ;
        $container->expects($this->once())
            ->method('get')
            ->with('app.my_controller')
            ->willReturn(new ImpossibleConstructController('toto', 'controller'))
        ;

        $resolver = $this->createControllerResolver(null, $container);

        $request = Request::create('/');
        $request->attributes->set('_controller', 'app.my_controller');
        $resolver->getController($request);
    }

    /**
     * @dataProvider getUndefinedControllers
     */
    public function testGetControllerOnNonUndefinedFunction($controller, $exceptionName = null, $exceptionMessage = null)
    {
        // All this logic needs to be duplicated, since calling parent::testGetControllerOnNonUndefinedFunction will override the expected excetion and not use the regex
        $resolver = $this->createControllerResolver();
        if (method_exists($this, 'expectException')) {
            $this->expectException($exceptionName);
            $this->expectExceptionMessageRegExp($exceptionMessage);
        } else {
            $this->setExpectedExceptionRegExp($exceptionName, $exceptionMessage);
        }

        $request = Request::create('/');
        $request->attributes->set('_controller', $controller);
        $resolver->getController($request);
    }

    public function getUndefinedControllers()
    {
        return [
            ['foo', \LogicException::class, '/Controller not found: service "foo" does not exist\./'],
            ['oof::bar', \InvalidArgumentException::class, '/Class "oof" does not exist\./'],
            ['stdClass', \LogicException::class, '/Controller not found: service "stdClass" does not exist\./'],
            [
                'Symfony\Component\HttpKernel\Tests\Controller\ControllerResolverTest::bar',
                \InvalidArgumentException::class,
                '/.?[cC]ontroller(.*?) for URI "\/" is not callable\.( Expected method(.*) Available methods)?/',
            ],
        ];
    }

    protected function createControllerResolver(LoggerInterface $logger = null, ContainerInterface $container = null)
    {
        if (!$container) {
            $container = $this->createMockContainer();
        }

        return new ContainerControllerResolver($container, $logger);
    }

    protected function createMockContainer()
    {
        return $this->getMockBuilder(ContainerInterface::class)->getMock();
    }
}

class InvokableController
{
    public function __construct($bar) // mandatory argument to prevent automatic instantiation
    {
    }

    public function __invoke()
    {
    }
}

abstract class NonInstantiableController
{
    public static function action()
    {
    }
}

class ImpossibleConstructController
{
    public function __construct($toto, $controller)
    {
    }

    public function action()
    {
    }
}
