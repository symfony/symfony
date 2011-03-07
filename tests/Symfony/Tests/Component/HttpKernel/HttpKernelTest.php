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
use Doctrine\Common\EventManager;

class HttpKernelTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException RuntimeException
     */
    public function testHandleWhenControllerThrowsAnExceptionAndRawIsTrue()
    {
        $kernel = new HttpKernel(new EventManager(), $this->getResolver(function () { throw new \RuntimeException(); }));

        $kernel->handle(new Request(), HttpKernelInterface::MASTER_REQUEST, true);
    }

    /**
     * @expectedException RuntimeException
     */
    public function testHandleWhenControllerThrowsAnExceptionAndRawIsFalseAndNoListenerIsRegistered()
    {
        $kernel = new HttpKernel(new EventManager(), $this->getResolver(function () { throw new \RuntimeException(); }));

        $kernel->handle(new Request(), HttpKernelInterface::MASTER_REQUEST, false);
    }

    public function testHandleWhenControllerThrowsAnExceptionAndRawIsFalse()
    {
        $evm = new EventManager();
        $evm->addEventListener(Events::onCoreException, function ($eventArgs)
        {
            $eventArgs->setResponse(new Response($eventArgs->getException()->getMessage()));
        });

        $kernel = new HttpKernel($evm, $this->getResolver(function () { throw new \RuntimeException('foo'); }));

        $this->assertEquals('foo', $kernel->handle(new Request())->getContent());
    }

    public function testHandleWhenAListenerReturnsAResponse()
    {
        $evm = new EventManager();
        $evm->addEventListener(Events::onCoreRequest, function ($eventArgs)
        {
            $eventArgs->setResponse(new Response('hello'));
        });

        $kernel = new HttpKernel($evm, $this->getResolver());

        $this->assertEquals('hello', $kernel->handle(new Request())->getContent());
    }

    /**
     * @expectedException Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function testHandleWhenNoControllerIsFound()
    {
        $evm = new EventManager();
        $kernel = new HttpKernel($evm, $this->getResolver(false));

        $kernel->handle(new Request());
    }

    /**
     * @expectedException LogicException
     */
    public function testHandleWhenNoControllerIsNotACallable()
    {
        $evm = new EventManager();
        $kernel = new HttpKernel($evm, $this->getResolver('foobar'));

        $kernel->handle(new Request());
    }

    /**
     * @expectedException LogicException
     */
    public function testHandleWhenControllerDoesNotReturnAResponse()
    {
        $evm = new EventManager();
        $kernel = new HttpKernel($evm, $this->getResolver(function () { return 'foo'; }));

        $kernel->handle(new Request());
    }

    public function testHandleWhenControllerDoesNotReturnAResponseButAViewIsRegistered()
    {
        $evm = new EventManager();
        $evm->addEventListener(Events::onCoreView, function ($eventArgs)
        {
            $eventArgs->setResponse(new Response($eventArgs->getControllerResult()));
        });
        $kernel = new HttpKernel($evm, $this->getResolver(function () { return 'foo'; }));

        $this->assertEquals('foo', $kernel->handle(new Request())->getContent());
    }

    public function testHandleWithAResponseListener()
    {
        $evm = new EventManager();
        $evm->addEventListener(Events::filterCoreResponse, function ($eventArgs)
        {
            $eventArgs->setResponse(new Response('foo'));
        });
        $kernel = new HttpKernel($evm, $this->getResolver());

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
