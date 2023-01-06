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

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Exception\ConflictingHeadersException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\EventListener\ValidateRequestListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class ValidateRequestListenerTest extends TestCase
{
    protected function tearDown(): void
    {
        Request::setTrustedProxies([], -1);
    }

    public function testListenerThrowsWhenMainRequestHasInconsistentClientIps()
    {
        $this->expectException(ConflictingHeadersException::class);
        $dispatcher = new EventDispatcher();
        $kernel = $this->createMock(HttpKernelInterface::class);

        $request = new Request();
        $request->setTrustedProxies(['1.1.1.1'], Request::HEADER_X_FORWARDED_FOR | Request::HEADER_FORWARDED);
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        $request->headers->set('FORWARDED', 'for=2.2.2.2');
        $request->headers->set('X_FORWARDED_FOR', '3.3.3.3');

        $dispatcher->addListener(KernelEvents::REQUEST, [new ValidateRequestListener(), 'onKernelRequest']);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $dispatcher->dispatch($event, KernelEvents::REQUEST);
    }
}
