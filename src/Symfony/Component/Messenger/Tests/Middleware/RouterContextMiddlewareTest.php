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
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RequestContextAwareInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class RouterContextMiddlewareTest extends MiddlewareTestCase
{
    public function testMiddlewareStoreContext()
    {
        $context = new RequestContext('/', 'GET', 'symfony.com');
        $context->setParameters([
            '_locale' => 'ar',
            'tenant' => 'Tunisia',
            'enabled' => true,
            'optional' => null,
            'tenant_id' => new class {
                public function __toString(): string
                {
                    return '42';
                }
            },
            '_functions' => static fn () => 'not serializable',
        ]);

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
        $this->assertSame(['_locale' => 'ar', 'tenant' => 'Tunisia', 'enabled' => true, 'optional' => null, 'tenant_id' => '42'], $stamp->getParameters());
        $this->assertSame($stamp->getParameters(), unserialize(serialize($stamp))->getParameters());
    }

    public function testMiddlewareRestoreContext()
    {
        $router = $this->createMock(RequestContextAwareInterface::class);
        $context = new RequestContext('', 'POST', 'github.com');
        $context->setParameters(['_locale' => 'fr', '_functions' => new \stdClass(), 'worker_only' => 123]);

        $router
            ->expects($this->once())
            ->method('getContext')
            ->willReturn($context);

        $middleware = new RouterContextMiddleware($router);
        $envelope = new Envelope(new \stdClass(), [
            new ConsumedByWorkerStamp(),
            new RouterContextStamp('', 'GET', 'symfony.com', 'https', 80, 443, '/', '', ['_locale' => 'ar', 'tenant' => 'Tunisia']),
        ]);

        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware
            ->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (Envelope $envelope, StackInterface $stack) use ($context): Envelope {
                $this->assertSame('symfony.com', $context->getHost());
                $this->assertSame('ar', $context->getParameter('_locale'));
                $this->assertSame('Tunisia', $context->getParameter('tenant'));
                $this->assertSame(123, $context->getParameter('worker_only'));
                $this->assertInstanceOf(\stdClass::class, $context->getParameter('_functions'));

                return $envelope;
            })
        ;

        $middleware->handle($envelope, new StackMiddleware($nextMiddleware));

        $this->assertSame('github.com', $context->getHost());
        $this->assertSame(['_locale' => 'fr', '_functions' => $context->getParameter('_functions'), 'worker_only' => 123], $context->getParameters());
        $this->assertNull($context->getParameter('tenant'));
    }

    public function testLocalizedUrlsAreGeneratedInTheOriginalLocaleOnWorkers()
    {
        $dispatchContext = new RequestContext('', 'GET', 'example.com');
        $dispatchContext->setParameter('_locale', 'ar');
        $router = $this->createStub(RequestContextAwareInterface::class);
        $router->method('getContext')->willReturn($dispatchContext);

        $middleware = new RouterContextMiddleware($router);
        $envelope = $middleware->handle(new Envelope(new \stdClass()), $this->getStackMock());

        // Simulate deserializing the message on another worker with a different locale.
        $workerContext = new RequestContext('', 'GET', 'localhost');
        $workerContext->setParameter('_locale', 'fr');
        $workerRouter = $this->createStub(RequestContextAwareInterface::class);
        $workerRouter->method('getContext')->willReturn($workerContext);

        $routes = new RouteCollection();
        $routes->add('reset_password', new Route('/{_locale}/reset-password/{token}'));
        $generator = new UrlGenerator($routes, $workerContext);

        $next = $this->createMock(MiddlewareInterface::class);
        $next->expects($this->once())->method('handle')->willReturnCallback(function (Envelope $envelope) use ($generator): Envelope {
            $this->assertSame('/ar/reset-password/abc', $generator->generate('reset_password', ['token' => 'abc']));

            return $envelope;
        });

        (new RouterContextMiddleware($workerRouter))->handle($envelope->with(new ConsumedByWorkerStamp()), new StackMiddleware($next));

        $this->assertSame('/fr/reset-password/abc', $generator->generate('reset_password', ['token' => 'abc']));
    }

    public function testMiddlewareRestoresParametersAfterException()
    {
        $context = new RequestContext('', 'GET', 'localhost');
        $context->setParameters(['_locale' => 'fr', 'worker_only' => true]);
        $router = $this->createStub(RequestContextAwareInterface::class);
        $router->method('getContext')->willReturn($context);

        $envelope = new Envelope(new \stdClass(), [
            new ConsumedByWorkerStamp(),
            new RouterContextStamp('', 'GET', 'symfony.com', 'https', 80, 443, '/', '', ['_locale' => 'ar']),
        ]);

        $next = $this->createStub(MiddlewareInterface::class);
        $next->method('handle')->willReturnCallback(function () use ($context): never {
            $this->assertSame('ar', $context->getParameter('_locale'));
            $this->assertTrue($context->getParameter('worker_only'));

            throw new \RuntimeException('Handler failed.');
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Handler failed.');

        try {
            (new RouterContextMiddleware($router))->handle($envelope, new StackMiddleware($next));
        } finally {
            $this->assertSame(['_locale' => 'fr', 'worker_only' => true], $context->getParameters());
            $this->assertSame('localhost', $context->getHost());
        }
    }

    public function testOldStampDoesNotClearWorkerParameters()
    {
        $context = new RequestContext('', 'POST', 'localhost');
        $context->setParameter('_locale', 'ar');
        $router = $this->createStub(RequestContextAwareInterface::class);
        $router->method('getContext')->willReturn($context);

        // A stamp serialized before the parameters property existed
        $property = "\0".RouterContextStamp::class."\0parameters";
        $serialized = str_replace([':9:{', \sprintf('s:%d:"%s";a:0:{}', \strlen($property), $property)], [':8:{', ''], serialize(new RouterContextStamp('', 'GET', 'symfony.com', 'https', 80, 443, '/', '')));
        $this->assertStringNotContainsString('parameters', $serialized);

        $envelope = new Envelope(new \stdClass(), [
            new ConsumedByWorkerStamp(),
            unserialize($serialized),
        ]);

        $next = $this->createStub(MiddlewareInterface::class);
        $next->method('handle')->willReturnCallback(function (Envelope $envelope) use ($context): Envelope {
            $this->assertSame('ar', $context->getParameter('_locale'));

            return $envelope;
        });

        (new RouterContextMiddleware($router))->handle($envelope, new StackMiddleware($next));
        $this->assertSame('ar', $context->getParameter('_locale'));
    }
}
