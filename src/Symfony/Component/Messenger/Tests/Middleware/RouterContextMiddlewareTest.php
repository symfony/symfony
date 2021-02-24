<?php

namespace Symfony\Component\Messenger\Tests\Middleware;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\RouterContextMiddleware;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\RouterContextStamp;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RequestContextAwareInterface;

class RouterContextMiddlewareTest extends MiddlewareTestCase
{
    public function testMiddlewareStoreContext()
    {
        $context = new RequestContext('/', 'GET', 'symfony.com');

        $router = $this->createMock(RequestContextAwareInterface::class);
        $router
            ->expects($this->once())
            ->method('getContext')
            ->willReturn($context);

        $middleware = new RouterContextMiddleware($router);

        $envelope = new Envelope(new \stdClass());
        $envelope = $middleware->handle($envelope, $this->getStackMock());

        $this->assertNotNull($stamp = $envelope->last(RouterContextStamp::class));
        $this->assertSame('symfony.com', $stamp->getHost());
    }

    public function testMiddlewareRestoreContext()
    {
        $router = $this->createMock(RequestContextAwareInterface::class);
        $originalContext = new RequestContext();

        $router
            ->expects($this->once())
            ->method('getContext')
            ->willReturn($originalContext);

        $router
            ->expects($this->exactly(2))
            ->method('setContext')
            ->withConsecutive(
                [$this->callback(function ($context) {
                    $this->assertSame('symfony.com', $context->getHost());

                    return true;
                })],
                [$originalContext]
            );

        $middleware = new RouterContextMiddleware($router);
        $envelope = new Envelope(new \stdClass(), [
            new ConsumedByWorkerStamp(),
            new RouterContextStamp('', 'GET', 'symfony.com', 'https', 80, 443, '/', ''),
        ]);
        $middleware->handle($envelope, $this->getStackMock());
    }
}
