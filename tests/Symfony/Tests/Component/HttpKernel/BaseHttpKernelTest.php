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

use Symfony\Component\HttpKernel\BaseHttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\EventDispatcher\EventDispatcher;

class BaseHttpKernelTest extends \PHPUnit_Framework_TestCase
{
    public function testHandleChangingMasterRequest()
    {
        $kernel = new BaseHttpKernel(new EventDispatcher(), $this->getResolver());

        $kernel->handle();
        $this->assertInstanceof('Symfony\Component\HttpFoundation\Request', $kernel->getRequest());

        $request = Request::create('/');
        $kernel->handle($request);
        $this->assertSame($request, $kernel->getRequest());

        $subRequest = Request::create('/');
        $kernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
        $this->assertSame($request, $kernel->getRequest());
    }

    /**
     * @expectedException RuntimeException
     */
    public function testHandleWhenControllerThrowsAnExceptionAndRawIsTrue()
    {
        $kernel = new BaseHttpKernel(new EventDispatcher(), $this->getResolver(function () { throw new \RuntimeException(); }));

        $kernel->handle(null, HttpKernelInterface::MASTER_REQUEST, true);
    }

    /**
     * @expectedException RuntimeException
     */
    public function testHandleWhenControllerThrowsAnExceptionAndRawIsFalseAndNoListenerIsRegistered()
    {
        $kernel = new BaseHttpKernel(new EventDispatcher(), $this->getResolver(function () { throw new \RuntimeException(); }));

        $kernel->handle(null, HttpKernelInterface::MASTER_REQUEST, false);
    }

    public function testHandleWhenControllerThrowsAnExceptionAndRawIsFalse()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->connect('core.exception', function ($event)
        {
            $event->setReturnValue(new Response($event->getParameter('exception')->getMessage()));

            return true;
        });

        $kernel = new BaseHttpKernel($dispatcher, $this->getResolver(function () { throw new \RuntimeException('foo'); }));

        $this->assertEquals('foo', $kernel->handle()->getContent());
    }

    public function testHandleWhenAListenerReturnsAResponse()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->connect('core.request', function ($event)
        {
            $event->setReturnValue(new Response('hello'));

            return true;
        });

        $kernel = new BaseHttpKernel($dispatcher, $this->getResolver());

        $this->assertEquals('hello', $kernel->handle()->getContent());
    }

    /**
     * @expectedException Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function testHandleWhenNoControllerIsFound()
    {
        $dispatcher = new EventDispatcher();
        $kernel = new BaseHttpKernel($dispatcher, $this->getResolver(false));

        $kernel->handle();
    }

    /**
     * @expectedException LogicException
     */
    public function testHandleWhenNoControllerIsNotACallable()
    {
        $dispatcher = new EventDispatcher();
        $kernel = new BaseHttpKernel($dispatcher, $this->getResolver('foobar'));

        $kernel->handle();
    }

    /**
     * @expectedException RuntimeException
     */
    public function testHandleWhenControllerDoesNotReturnAResponse()
    {
        $dispatcher = new EventDispatcher();
        $kernel = new BaseHttpKernel($dispatcher, $this->getResolver(function () { return 'foo'; }));

        $kernel->handle();
    }

    public function testHandleWhenControllerDoesNotReturnAResponseButAViewIsRegistered()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->connect('core.view', function ($event, $retval)
        {
            return new Response($retval);
        });
        $kernel = new BaseHttpKernel($dispatcher, $this->getResolver(function () { return 'foo'; }));

        $this->assertEquals('foo', $kernel->handle()->getContent());
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
        $kernel = new BaseHttpKernel($dispatcher, $this->getResolver(function () { return 'foo'; }));

        $kernel->handle();
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
        $kernel = new BaseHttpKernel($dispatcher, $this->getResolver());

        $kernel->handle();
    }

    public function testHandleWithAResponseListener()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->connect('core.response', function ($event, $response)
        {
            return new Response('foo');
        });
        $kernel = new BaseHttpKernel($dispatcher, $this->getResolver());

        $this->assertEquals('foo', $kernel->handle()->getContent());
    }

    protected function getResolver($controller = null)
    {
        if (null === $controller) {
            $controller = function () { return new Response('Hello'); };
        }
        $resolver = $this->getMock('Symfony\Component\HttpKernel\Controller\ControllerResolverInterface');
        $resolver->expects($this->any())
                 ->method('getController')
                 ->will($this->returnValue($controller))
        ;
        $resolver->expects($this->any())
                 ->method('getArguments')
                 ->will($this->returnValue(array()))
        ;

        return $resolver;
    }
}
