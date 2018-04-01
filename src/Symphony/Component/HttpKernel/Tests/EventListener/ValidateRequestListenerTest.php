<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symphony\Component\EventDispatcher\EventDispatcher;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpKernel\EventListener\ValidateRequestListener;
use Symphony\Component\HttpKernel\Event\GetResponseEvent;
use Symphony\Component\HttpKernel\HttpKernelInterface;
use Symphony\Component\HttpKernel\KernelEvents;

class ValidateRequestListenerTest extends TestCase
{
    /**
     * @expectedException \Symphony\Component\HttpFoundation\Exception\ConflictingHeadersException
     */
    public function testListenerThrowsWhenMasterRequestHasInconsistentClientIps()
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getMockBuilder('Symphony\Component\HttpKernel\HttpKernelInterface')->getMock();

        $request = new Request();
        $request->setTrustedProxies(array('1.1.1.1'), Request::HEADER_X_FORWARDED_FOR | Request::HEADER_FORWARDED);
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        $request->headers->set('FORWARDED', 'for=2.2.2.2');
        $request->headers->set('X_FORWARDED_FOR', '3.3.3.3');

        $dispatcher->addListener(KernelEvents::REQUEST, array(new ValidateRequestListener(), 'onKernelRequest'));
        $event = new GetResponseEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST);

        $dispatcher->dispatch(KernelEvents::REQUEST, $event);
    }
}
