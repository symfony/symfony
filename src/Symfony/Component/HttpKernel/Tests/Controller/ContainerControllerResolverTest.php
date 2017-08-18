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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ContainerControllerResolver;

class ContainerControllerResolverTest extends ControllerResolverTest
{
    public function testGetControllerService()
    {
        $container = $this->createMockContainer();
        $container->expects($this->once())
            ->method('get')
            ->with('foo')
            ->will($this->returnValue($this))
        ;

        $resolver = $this->createControllerResolver(null, $container);
        $request = Request::create('/');
        $request->attributes->set('_controller', 'foo:controllerMethod1');

        $controller = $resolver->getController($request);

        $this->assertInstanceOf(get_class($this), $controller[0]);
        $this->assertSame('controllerMethod1', $controller[1]);
    }

    public function testGetControllerInvokableService()
    {
        $invokableController = new InvokableController('bar');

        $container = $this->createMockContainer();
        $container->expects($this->once())
            ->method('has')
            ->with('foo')
            ->will($this->returnValue(true))
        ;
        $container->expects($this->once())
            ->method('get')
            ->with('foo')
            ->will($this->returnValue($invokableController))
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
            ->will($this->returnValue(true))
        ;
        $container->expects($this->once())
            ->method('get')
            ->with($className)
            ->will($this->returnValue($invokableController))
        ;

        $resolver = $this->createControllerResolver(null, $container);
        $request = Request::create('/');
        $request->attributes->set('_controller', $className);

        $controller = $resolver->getController($request);

        $this->assertEquals($invokableController, $controller);
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
        return array(
            array('foo', \LogicException::class, '/Unable to parse the controller name "foo"\./'),
            array('oof::bar', \InvalidArgumentException::class, '/Class "oof" does not exist\./'),
            array('stdClass', \LogicException::class, '/Unable to parse the controller name "stdClass"\./'),
            array(
                'Symfony\Component\HttpKernel\Tests\Controller\ControllerResolverTest::bar',
                \InvalidArgumentException::class,
                '/.?[cC]ontroller(.*?) for URI "\/" is not callable\.( Expected method(.*) Available methods)?/',
            ),
        );
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
