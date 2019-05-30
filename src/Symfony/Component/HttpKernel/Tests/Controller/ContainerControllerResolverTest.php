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
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ContainerControllerResolver;

class ContainerControllerResolverTest extends ControllerResolverTest
{
    public function testGetControllerServiceWithSingleColon()
    {
        $service = new ControllerTestService('foo');

        $container = $this->createMockContainer();
        $container->expects($this->once())
            ->method('has')
            ->with('foo')
            ->willReturn(true);
        $container->expects($this->once())
            ->method('get')
            ->with('foo')
            ->willReturn($service)
        ;

        $resolver = $this->createControllerResolver(null, $container);
        $request = Request::create('/');
        $request->attributes->set('_controller', 'foo:action');

        $controller = $resolver->getController($request);

        $this->assertSame($service, $controller[0]);
        $this->assertSame('action', $controller[1]);
    }

    public function testGetControllerService()
    {
        $service = new ControllerTestService('foo');

        $container = $this->createMockContainer();
        $container->expects($this->once())
            ->method('has')
            ->with('foo')
            ->willReturn(true);
        $container->expects($this->once())
            ->method('get')
            ->with('foo')
            ->willReturn($service)
        ;

        $resolver = $this->createControllerResolver(null, $container);
        $request = Request::create('/');
        $request->attributes->set('_controller', 'foo::action');

        $controller = $resolver->getController($request);

        $this->assertSame($service, $controller[0]);
        $this->assertSame('action', $controller[1]);
    }

    public function testGetControllerInvokableService()
    {
        $service = new InvokableControllerService('bar');

        $container = $this->createMockContainer();
        $container->expects($this->once())
            ->method('has')
            ->with('foo')
            ->willReturn(true)
        ;
        $container->expects($this->once())
            ->method('get')
            ->with('foo')
            ->willReturn($service)
        ;

        $resolver = $this->createControllerResolver(null, $container);
        $request = Request::create('/');
        $request->attributes->set('_controller', 'foo');

        $controller = $resolver->getController($request);

        $this->assertSame($service, $controller);
    }

    public function testGetControllerInvokableServiceWithClassNameAsName()
    {
        $service = new InvokableControllerService('bar');

        $container = $this->createMockContainer();
        $container->expects($this->once())
            ->method('has')
            ->with(InvokableControllerService::class)
            ->willReturn(true)
        ;
        $container->expects($this->once())
            ->method('get')
            ->with(InvokableControllerService::class)
            ->willReturn($service)
        ;

        $resolver = $this->createControllerResolver(null, $container);
        $request = Request::create('/');
        $request->attributes->set('_controller', InvokableControllerService::class);

        $controller = $resolver->getController($request);

        $this->assertSame($service, $controller);
    }

    /**
     * Tests where the fallback instantiation fails due to required constructor arguments.
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Controller "Symfony\Component\HttpKernel\Tests\Controller\ControllerTestService" cannot be fetched from the container because it is private. Did you forget to tag the service with "controller.service_arguments"?
     */
    public function testExceptionWhenUsingRemovedControllerServiceWithClassNameAsName()
    {
        $container = $this->getMockBuilder(Container::class)->getMock();
        $container->expects($this->once())
            ->method('has')
            ->with(ControllerTestService::class)
            ->willReturn(false)
        ;

        $container->expects($this->atLeastOnce())
            ->method('getRemovedIds')
            ->with()
            ->willReturn([ControllerTestService::class => true])
        ;

        $resolver = $this->createControllerResolver(null, $container);
        $request = Request::create('/');
        $request->attributes->set('_controller', [ControllerTestService::class, 'action']);

        $resolver->getController($request);
    }

    /**
     * Tests where the fallback instantiation fails due to non-existing class.
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Controller "app.my_controller" cannot be fetched from the container because it is private. Did you forget to tag the service with "controller.service_arguments"?
     */
    public function testExceptionWhenUsingRemovedControllerService()
    {
        $container = $this->getMockBuilder(Container::class)->getMock();
        $container->expects($this->once())
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

    public function getUndefinedControllers()
    {
        $tests = parent::getUndefinedControllers();
        $tests[0] = ['foo', \InvalidArgumentException::class, 'Controller "foo" does neither exist as service nor as class'];
        $tests[1] = ['oof::bar', \InvalidArgumentException::class, 'Controller "oof" does neither exist as service nor as class'];
        $tests[2] = [['oof', 'bar'], \InvalidArgumentException::class, 'Controller "oof" does neither exist as service nor as class'];
        $tests[] = [
            [ControllerTestService::class, 'action'],
            \InvalidArgumentException::class,
            'Controller "Symfony\Component\HttpKernel\Tests\Controller\ControllerTestService" has required constructor arguments and does not exist in the container. Did you forget to define such a service?',
        ];
        $tests[] = [
            ControllerTestService::class.'::action',
            \InvalidArgumentException::class, 'Controller "Symfony\Component\HttpKernel\Tests\Controller\ControllerTestService" has required constructor arguments and does not exist in the container. Did you forget to define such a service?',
        ];
        $tests[] = [
            InvokableControllerService::class,
            \InvalidArgumentException::class,
            'Controller "Symfony\Component\HttpKernel\Tests\Controller\InvokableControllerService" has required constructor arguments and does not exist in the container. Did you forget to define such a service?',
        ];

        return $tests;
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

class InvokableControllerService
{
    public function __construct($bar) // mandatory argument to prevent automatic instantiation
    {
    }

    public function __invoke()
    {
    }
}

class ControllerTestService
{
    public function __construct($foo)
    {
    }

    public function action()
    {
    }
}
