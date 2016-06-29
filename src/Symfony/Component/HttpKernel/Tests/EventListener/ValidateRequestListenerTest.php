<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\EventListener;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Exception\ConflictingHeadersException;
use Symfony\Component\HttpKernel\EventListener\ValidateRequestListener;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class ValidateRequestListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testListenerThrowsWhenMasterRequestHasInconsistentClientIps()
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');
        $listener = new ValidateRequestListener();
        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $request->method('getClientIps')
            ->will($this->throwException(new ConflictingHeadersException()));

        $dispatcher->addListener(KernelEvents::REQUEST, array($listener, 'onKernelRequest'));
        $event = new GetResponseEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST);

        $this->setExpectedException('Symfony\Component\HttpKernel\Exception\BadRequestHttpException');
        $dispatcher->dispatch(KernelEvents::REQUEST, $event);
    }

    public function testListenerDoesNothingOnValidRequests()
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');
        $listener = new ValidateRequestListener();
        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $request->method('getClientIps')
            ->willReturn(array('127.0.0.1'));

        $dispatcher->addListener(KernelEvents::REQUEST, array($listener, 'onKernelRequest'));
        $event = new GetResponseEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST);
        $dispatcher->dispatch(KernelEvents::REQUEST, $event);
    }

    public function testListenerDoesNothingOnSubrequests()
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');
        $listener = new ValidateRequestListener();
        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $request->method('getClientIps')
            ->will($this->throwException(new ConflictingHeadersException()));

        $dispatcher->addListener(KernelEvents::REQUEST, array($listener, 'onKernelRequest'));
        $event = new GetResponseEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);
        $dispatcher->dispatch(KernelEvents::REQUEST, $event);
    }
}
