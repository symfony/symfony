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
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\EventListener\SessionListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class SessionListenerTest extends TestCase
{
    public function testOnlyTriggeredOnMasterRequest()
    {
        $listener = $this->getMockForAbstractClass(AbstractSessionListener::class);
        $event = $this->getMockBuilder(GetResponseEvent::class)->disableOriginalConstructor()->getMock();
        $event->expects($this->once())->method('isMasterRequest')->willReturn(false);
        $event->expects($this->never())->method('getRequest');

        // sub request
        $listener->onKernelRequest($event);
    }

    public function testSessionIsSet()
    {
        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();

        $container = new Container();
        $container->set('session', $session);

        $request = new Request();
        $listener = new SessionListener($container);

        $event = $this->getMockBuilder(GetResponseEvent::class)->disableOriginalConstructor()->getMock();
        $event->expects($this->once())->method('isMasterRequest')->willReturn(true);
        $event->expects($this->once())->method('getRequest')->willReturn($request);

        $listener->onKernelRequest($event);

        $this->assertTrue($request->hasSession());
        $this->assertSame($session, $request->getSession());
    }

    public function testResponseIsPrivate()
    {
        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $session->expects($this->exactly(2))->method('getUsageIndex')->will($this->onConsecutiveCalls(0, 1));

        $container = new Container();
        $container->set('session', $session);

        $listener = new SessionListener($container);
        $kernel = $this->getMockBuilder(HttpKernelInterface::class)->disableOriginalConstructor()->getMock();

        $request = new Request();
        $response = new Response();
        $listener->onKernelRequest(new GetResponseEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST));
        $listener->onKernelResponse(new FilterResponseEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST, $response));

        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertTrue($response->headers->hasCacheControlDirective('must-revalidate'));
        $this->assertSame('0', $response->headers->getCacheControlDirective('max-age'));

        $this->assertTrue($response->headers->has('Expires'));
        $this->assertLessThanOrEqual((new \DateTime('now', new \DateTimeZone('UTC'))), (new \DateTime($response->headers->get('Expires'))));
    }

    public function testSurrogateMasterRequestIsPublic()
    {
        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $session->expects($this->exactly(4))->method('getUsageIndex')->will($this->onConsecutiveCalls(0, 1, 1, 1));

        $container = new Container();
        $container->set('session', $session);

        $listener = new SessionListener($container);
        $kernel = $this->getMockBuilder(HttpKernelInterface::class)->disableOriginalConstructor()->getMock();

        $request = new Request();
        $response = new Response();
        $response->setCache(['public' => true, 'max_age' => '30']);
        $listener->onKernelRequest(new GetResponseEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST));
        $this->assertTrue($request->hasSession());

        $subRequest = clone $request;
        $this->assertSame($request->getSession(), $subRequest->getSession());
        $listener->onKernelRequest(new GetResponseEvent($kernel, $subRequest, HttpKernelInterface::MASTER_REQUEST));
        $listener->onKernelResponse(new FilterResponseEvent($kernel, $subRequest, HttpKernelInterface::MASTER_REQUEST, $response));
        $listener->onFinishRequest(new FinishRequestEvent($kernel, $subRequest, HttpKernelInterface::MASTER_REQUEST));

        $this->assertFalse($response->headers->hasCacheControlDirective('private'));
        $this->assertFalse($response->headers->hasCacheControlDirective('must-revalidate'));
        $this->assertSame('30', $response->headers->getCacheControlDirective('max-age'));

        $this->assertFalse($response->headers->has('Expires'));

        $listener->onKernelResponse(new FilterResponseEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST, $response));

        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertTrue($response->headers->hasCacheControlDirective('must-revalidate'));
        $this->assertSame('0', $response->headers->getCacheControlDirective('max-age'));

        $this->assertTrue($response->headers->has('Expires'));
        $this->assertLessThanOrEqual((new \DateTime('now', new \DateTimeZone('UTC'))), (new \DateTime($response->headers->get('Expires'))));
    }
}
