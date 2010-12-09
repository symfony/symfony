<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpKernel;

use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\EventDispatcher\EventDispatcher;

class HttpKernelTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException RuntimeException
     */
    public function testHandleWhenControllerThrowsAnExceptionAndRawIsTrue()
    {
        $kernel = new HttpKernel(new EventDispatcher(), $this->getResolver(function () { throw new \RuntimeException(); }));

        $kernel->handle(new Request(), HttpKernelInterface::MASTER_REQUEST, true);
    }

    /**
     * @expectedException RuntimeException
     */
    public function testHandleWhenControllerThrowsAnExceptionAndRawIsFalseAndNoListenerIsRegistered()
    {
        $kernel = new HttpKernel(new EventDispatcher(), $this->getResolver(function () { throw new \RuntimeException(); }));

        $kernel->handle(new Request(), HttpKernelInterface::MASTER_REQUEST, false);
    }

    public function testHandleWhenControllerThrowsAnExceptionAndRawIsFalse()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->connect('core.exception', function ($event)
        {
            $event->setReturnValue(new Response($event->get('exception')->getMessage()));

            return true;
        });

        $kernel = new HttpKernel($dispatcher, $this->getResolver(function () { throw new \RuntimeException('foo'); }));

        $this->assertEquals('foo', $kernel->handle(new Request())->getContent());
    }

    public function testHandleWhenAListenerReturnsAResponse()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->connect('core.request', function ($event)
        {
            $event->setReturnValue(new Response('hello'));

            return true;
        });

        $kernel = new HttpKernel($dispatcher, $this->getResolver());

        $this->assertEquals('hello', $kernel->handle(new Request())->getContent());
    }

    /**
     * @expectedException Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function testHandleWhenNoControllerIsFound()
    {
        $dispatcher = new EventDispatcher();
        $kernel = new HttpKernel($dispatcher, $this->getResolver(false));

        $kernel->handle(new Request());
    }

    /**
     * @expectedException LogicException
     */
    public function testHandleWhenNoControllerIsNotACallable()
    {
        $dispatcher = new EventDispatcher();
        $kernel = new HttpKernel($dispatcher, $this->getResolver('foobar'));

        $kernel->handle(new Request());
    }

    /**
     * @expectedException RuntimeException
     */
    public function testHandleWhenControllerDoesNotReturnAResponse()
    {
        $dispatcher = new EventDispatcher();
        $kernel = new HttpKernel($dispatcher, $this->getResolver(function () { return 'foo'; }));

        $kernel->handle(new Request());
    }

    public function testHandleWhenControllerDoesNotReturnAResponseButAViewIsRegistered()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->connect('core.view', function ($event, $retval)
        {
            return new Response($retval);
        });
        $kernel = new HttpKernel($dispatcher, $this->getResolver(function () { return 'foo'; }));

        $this->assertEquals('foo', $kernel->handle(new Request())->getContent());
    }

    /**
     * @expectedException RuntimeException
     */
    public function testHandleWhenAViewDoesNotReturnAResponse()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->connect('core.view', function ($event, $retval)
        {
            return $retval;
        });
        $kernel = new HttpKernel($dispatcher, $this->getResolver(function () { return 'foo'; }));

        $kernel->handle(new Request());
    }

    /**
     * @expectedException RuntimeException
     */
    public function testHandleWhenAResponseListenerDoesNotReturnAResponse()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->connect('core.response', function ($event, $response)
        {
            return 'foo';
        });
        $kernel = new HttpKernel($dispatcher, $this->getResolver());

        $kernel->handle(new Request());
    }

    public function testHandleWithAResponseListener()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->connect('core.response', function ($event, $response)
        {
            return new Response('foo');
        });
        $kernel = new HttpKernel($dispatcher, $this->getResolver());

        $this->assertEquals('foo', $kernel->handle(new Request())->getContent());
    }

    /**
     * @testdox A master request should be set on the kernel for the duration of handle(), then unset
     */
    public function testHandleSetsTheCurrentRequest()
    {
        $dispatcher = new EventDispatcher();
        $resolver = $this->getMock('Symfony\\Component\\HttpKernel\\Controller\\ControllerResolverInterface');
        $kernel = new HttpKernel($dispatcher, $resolver);

        $request = new Request();
        $expected = new Response();

        $testCase = $this;
        $controller = function() use($expected, $kernel, $testCase, $request)
        {
            $testCase->assertSame($request, $kernel->getRequest(), '->handle() sets the current request when there is no parent request');
            return $expected;
        };

        $resolver->expects($this->once())
            ->method('getController')
            ->with($request)
            ->will($this->returnValue($controller));
        $resolver->expects($this->once())
            ->method('getArguments')
            ->with($request, $controller)
            ->will($this->returnValue(array()));

        $actual = $kernel->handle($request);

        $this->assertSame($expected, $actual, '->handle() returns the response');
        $this->assertNull($kernel->getRequest(), '->handle() restores the parent (null) request');
    }

    /**
     * @testdox The parent request is restored following a sub request
     * @dataProvider provideRequestTypes
     */
    public function testHandleRestoresThePreviousRequest($requestType)
    {
        $dispatcher = new EventDispatcher();
        $resolver = $this->getMock('Symfony\\Component\\HttpKernel\\Controller\\ControllerResolverInterface');
        $kernel = new HttpKernel($dispatcher, $resolver);

        $parentRequest = new Request(array('name' => 'parent_request'));
        $request = new Request(array('name' => 'current_request'));
        $expected = new Response();

        // sets a parent request to emulate a subrequest
        $reflProp = new \ReflectionProperty($kernel, 'request');
        $reflProp->setAccessible(true);
        $reflProp->setValue($kernel, $parentRequest);

        $testCase = $this;
        $controller = function() use($expected, $kernel, $testCase, $request)
        {
            $testCase->assertSame($request, $kernel->getRequest(), '->handle() sets the current request when there is a parent request');
            return $expected;
        };

        $resolver->expects($this->once())
            ->method('getController')
            ->with($request)
            ->will($this->returnValue($controller));
        $resolver->expects($this->once())
            ->method('getArguments')
            ->with($request, $controller)
            ->will($this->returnValue(array()));

        // the behavior should be the same, regardless of request type
        $actual = $kernel->handle($request, $requestType);

        $this->assertSame($expected, $actual, '->handle() returns the response');
        $this->assertSame($parentRequest, $kernel->getRequest(), '->handle() restores the parent request');
    }

    public function provideRequestTypes()
    {
        return array(
            array(HttpKernelInterface::MASTER_REQUEST),
            array(HttpKernelInterface::SUB_REQUEST),
        );
    }

    public function testHandleRestoresThePreviousRequestOnException()
    {
        $dispatcher = new EventDispatcher();
        $resolver = $this->getMock('Symfony\\Component\\HttpKernel\\Controller\\ControllerResolverInterface');
        $kernel = new HttpKernel($dispatcher, $resolver);
        $request = new Request();

        $expected = new \Exception();
        $controller = function() use ($expected)
        {
            throw $expected;
        };

        $resolver->expects($this->once())
            ->method('getController')
            ->with($request)
            ->will($this->returnValue($controller));
        $resolver->expects($this->once())
            ->method('getArguments')
            ->with($request, $controller)
            ->will($this->returnValue(array()));

        try {
            $kernel->handle($request);
            $this->fail('->handle() suppresses the controller exception');
        } catch (\Exception $actual) {
            $this->assertSame($expected, $actual, '->handle() throws the controller exception');
        }

        $this->assertNull($kernel->getRequest(), '->handle() restores the parent (null) request when the controller throws an exception');
    }

    protected function getResolver($controller = null)
    {
        if (null === $controller) {
            $controller = function() { return new Response('Hello'); };
        }

        $resolver = $this->getMock('Symfony\\Component\\HttpKernel\\Controller\\ControllerResolverInterface');
        $resolver->expects($this->any())
            ->method('getController')
            ->will($this->returnValue($controller));
        $resolver->expects($this->any())
            ->method('getArguments')
            ->will($this->returnValue(array()));

        return $resolver;
    }
}
