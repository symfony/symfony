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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\EventListener\ValidateRequestClientIpListener;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class ValidateRequestClientIpTest extends \PHPUnit_Framework_TestCase
{
    public function testListenerThrowsOnInconsistentRequests()
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');
        $listener = new ValidateRequestClientIpListener();
        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $request->method('getClientIps')
            ->will($this->throwException(new ConflictingHeadersException()));

        $dispatcher->addListener(KernelEvents::REQUEST, array($listener, 'onKernelRequest'));
        $event = new GetResponseEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);

        $this->setExpectedException('Symfony\Component\HttpKernel\Exception\HttpException');
        $dispatcher->dispatch(KernelEvents::REQUEST, $event);
    }

    public function testListenerDoesNothingOnConsistenRequests()
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');
        $listener = new ValidateRequestClientIpListener();
        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $request->method('getClientIps')
            ->willReturn(array('127.0.0.1'));

        $dispatcher->addListener(KernelEvents::REQUEST, array($listener, 'onKernelRequest'));
        $event = new GetResponseEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);
        $dispatcher->dispatch(KernelEvents::REQUEST, $event);
    }
}
