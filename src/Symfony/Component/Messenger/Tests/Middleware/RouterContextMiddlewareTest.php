<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Middleware;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\RouterContextMiddleware;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
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
        $context = new RequestContext('', 'POST', 'github.com');

        $router
            ->expects($this->once())
            ->method('getContext')
            ->willReturn($context);

        $middleware = new RouterContextMiddleware($router);
        $envelope = new Envelope(new \stdClass(), [
            new ConsumedByWorkerStamp(),
            new RouterContextStamp('', 'GET', 'symfony.com', 'https', 80, 443, '/', ''),
        ]);

        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware
            ->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (Envelope $envelope, StackInterface $stack) use ($context): Envelope {
                $this->assertSame('symfony.com', $context->getHost());

                return $envelope;
            })
        ;

        $middleware->handle($envelope, new StackMiddleware($nextMiddleware));

        $this->assertSame('github.com', $context->getHost());
    }
}
