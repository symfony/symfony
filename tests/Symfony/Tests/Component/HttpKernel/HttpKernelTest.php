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

use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Events;
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
        $dispatcher->addListener(Events::onCoreException, function ($event)
        {
            $event->setResponse(new Response($event->getException()->getMessage()));
        });

        $kernel = new HttpKernel($dispatcher, $this->getResolver(function () { throw new \RuntimeException('foo'); }));

        $this->assertEquals('foo', $kernel->handle(new Request())->getContent());
    }

    public function testHandleWhenAListenerReturnsAResponse()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(Events::onCoreRequest, function ($event)
        {
            $event->setResponse(new Response('hello'));
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
     * @expectedException LogicException
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
        $dispatcher->addListener(Events::onCoreView, function ($event)
        {
            $event->setResponse(new Response($event->getControllerResult()));
        });
        $kernel = new HttpKernel($dispatcher, $this->getResolver(function () { return 'foo'; }));

        $this->assertEquals('foo', $kernel->handle(new Request())->getContent());
    }

    public function testHandleWithAResponseListener()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(Events::onCoreResponse, function ($event)
        {
            $event->setResponse(new Response('foo'));
        });
        $kernel = new HttpKernel($dispatcher, $this->getResolver());

        $this->assertEquals('foo', $kernel->handle(new Request())->getContent());
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
