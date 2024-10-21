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
use Psr\Log\LoggerInterface;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\ErrorListener;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
use Symfony\Component\HttpKernel\Tests\Logger;

/**
 * @author Robert Schönthal <seroscho@googlemail.com>
 *
 * @group time-sensitive
 */
class ErrorListenerTest extends TestCase
{
    public function testConstruct()
    {
        $logger = new TestLogger();
        $l = new ErrorListener('foo', $logger);

        $_logger = new \ReflectionProperty(\get_class($l), 'logger');
        $_logger->setAccessible(true);
        $_controller = new \ReflectionProperty(\get_class($l), 'controller');
        $_controller->setAccessible(true);

        $this->assertSame($logger, $_logger->getValue($l));
        $this->assertSame('foo', $_controller->getValue($l));
    }

    /**
     * @dataProvider provider
     */
    public function testHandleWithoutLogger($event, $event2)
    {
        $initialErrorLog = ini_set('error_log', file_exists('/dev/null') ? '/dev/null' : 'nul');

        try {
            $l = new ErrorListener('foo');
            $l->logKernelException($event);
            $l->onKernelException($event);

            $this->assertEquals(new Response('foo'), $event->getResponse());

            try {
                $l->logKernelException($event2);
                $l->onKernelException($event2);
                $this->fail('RuntimeException expected');
            } catch (\RuntimeException $e) {
                $this->assertSame('bar', $e->getMessage());
                $this->assertSame('foo', $e->getPrevious()->getMessage());
            }
        } finally {
            ini_set('error_log', $initialErrorLog);
        }
    }

    /**
     * @dataProvider provider
     */
    public function testHandleWithLogger($event, $event2)
    {
        $logger = new TestLogger();

        $l = new ErrorListener('foo', $logger);
        $l->logKernelException($event);
        $l->onKernelException($event);

        $this->assertEquals(new Response('foo'), $event->getResponse());

        try {
            $l->logKernelException($event2);
            $l->onKernelException($event2);
            $this->fail('RuntimeException expected');
        } catch (\RuntimeException $e) {
            $this->assertSame('bar', $e->getMessage());
            $this->assertSame('foo', $e->getPrevious()->getMessage());
        }

        $this->assertEquals(3, $logger->countErrors());
        $this->assertCount(3, $logger->getLogs('critical'));
    }

    public function testHandleWithLoggerAndCustomConfiguration()
    {
        $request = new Request();
        $event = new ExceptionEvent(new TestKernel(), $request, HttpKernelInterface::MAIN_REQUEST, new \RuntimeException('bar'));
        $logger = new TestLogger();
        $l = new ErrorListener('not used', $logger, false, [
            \RuntimeException::class => [
                'log_level' => 'warning',
                'status_code' => 401,
            ],
        ]);
        $l->logKernelException($event);
        $l->onKernelException($event);

        $this->assertEquals(new Response('foo', 401), $event->getResponse());

        $this->assertEquals(0, $logger->countErrors());
        $this->assertCount(0, $logger->getLogs('critical'));
        $this->assertCount(1, $logger->getLogs('warning'));
    }

    public static function provider()
    {
        if (!class_exists(Request::class)) {
            return [[null, null]];
        }

        $request = new Request();
        $exception = new \Exception('foo');
        $event = new ExceptionEvent(new TestKernel(), $request, HttpKernelInterface::MAIN_REQUEST, $exception);
        $event2 = new ExceptionEvent(new TestKernelThatThrowsException(), $request, HttpKernelInterface::MAIN_REQUEST, $exception);

        return [
            [$event, $event2],
        ];
    }

    public function testSubRequestFormat()
    {
        $listener = new ErrorListener('foo', $this->createMock(LoggerInterface::class));

        $kernel = $this->createMock(HttpKernelInterface::class);
        $kernel->expects($this->once())->method('handle')->willReturnCallback(function (Request $request) {
            return new Response($request->getRequestFormat());
        });

        $request = Request::create('/');
        $request->setRequestFormat('xml');

        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, new \Exception('foo'));
        $listener->onKernelException($event);

        $response = $event->getResponse();
        $this->assertEquals('xml', $response->getContent());
    }

    public function testCSPHeaderIsRemoved()
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $kernel->expects($this->once())->method('handle')->willReturnCallback(function (Request $request) {
            return new Response($request->getRequestFormat());
        });

        $listener = new ErrorListener('foo', $this->createMock(LoggerInterface::class), true);

        $dispatcher->addSubscriber($listener);

        $request = Request::create('/');
        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, new \Exception('foo'));
        $dispatcher->dispatch($event, KernelEvents::EXCEPTION);

        $response = new Response('', 200, ['content-security-policy' => "style-src 'self'"]);
        $this->assertTrue($response->headers->has('content-security-policy'));

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $dispatcher->dispatch($event, KernelEvents::RESPONSE);

        $this->assertFalse($response->headers->has('content-security-policy'), 'CSP header has been removed');
    }

    /**
     * @dataProvider controllerProvider
     */
    public function testOnControllerArguments(callable $controller)
    {
        $listener = new ErrorListener($controller, $this->createMock(LoggerInterface::class), true);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $kernel->method('handle')->willReturnCallback(function (Request $request) use ($listener, $controller, $kernel) {
            $this->assertSame($controller, $request->attributes->get('_controller'));
            $arguments = (new ArgumentResolver())->getArguments($request, $controller);
            $event = new ControllerArgumentsEvent($kernel, $controller, $arguments, $request, HttpKernelInterface::SUB_REQUEST);
            $listener->onControllerArguments($event);

            return $controller(...$event->getArguments());
        });

        $event = new ExceptionEvent($kernel, Request::create('/'), HttpKernelInterface::MAIN_REQUEST, new \Exception('foo'));
        $listener->onKernelException($event);

        $this->assertSame('OK: foo', $event->getResponse()->getContent());
    }

    public static function controllerProvider()
    {
        yield [function (FlattenException $exception) {
            return new Response('OK: '.$exception->getMessage());
        }];

        yield [function ($exception) {
            static::assertInstanceOf(FlattenException::class, $exception);

            return new Response('OK: '.$exception->getMessage());
        }];

        yield [function (\Throwable $exception) {
            return new Response('OK: '.$exception->getMessage());
        }];
    }
}

class TestLogger extends Logger implements DebugLoggerInterface
{
    public function countErrors(?Request $request = null): int
    {
        return \count($this->logs['critical']);
    }
}

class TestKernel implements HttpKernelInterface
{
    public function handle(Request $request, $type = self::MAIN_REQUEST, $catch = true): Response
    {
        $e = $request->attributes->get('exception');
        if ($e instanceof HttpExceptionInterface) {
            return new Response('foo', $e->getStatusCode(), $e->getHeaders());
        }

        return new Response('foo');
    }
}

class TestKernelThatThrowsException implements HttpKernelInterface
{
    public function handle(Request $request, $type = self::MAIN_REQUEST, $catch = true): Response
    {
        throw new \RuntimeException('bar');
    }
}
