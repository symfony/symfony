<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Runtime\Tests;

require_once __DIR__.'/frankenphp-function-mock.php';

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\Runtime\Runner\FrankenPhpWorkerRunner;

interface TestAppInterface extends HttpKernelInterface, TerminableInterface
{
}

class FrankenPhpWorkerRunnerTest extends TestCase
{
    public static int $requests = 1;
    public static bool $handlingRequest = false;
    public static int $completedRequests = 0;
    public static ?array $reportedExceptions = null;

    protected function tearDown(): void
    {
        self::$requests = 1;
        self::$completedRequests = 0;
        self::$reportedExceptions = null;
    }

    public function testRun()
    {
        $application = $this->createMock(TestAppInterface::class);
        $application
            ->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (Request $request, int $type = HttpKernelInterface::MAIN_REQUEST, bool $catch = true): Response {
                $this->assertSame('bar', $request->server->get('FOO'));

                return new Response();
            });
        $application->expects($this->once())->method('terminate');

        $server = $_SERVER;

        try {
            $_SERVER['FOO'] = 'bar';

            $runner = new FrankenPhpWorkerRunner($application, 500);
            $this->assertSame(0, $runner->run());
        } finally {
            $_SERVER = $server;
        }
    }

    public function testKernelTerminatesBeforeTheRequestEnds()
    {
        $response = $this->createMock(Response::class);
        $flushed = false;
        // HttpFoundation 6.4 does not declare the $flush parameter of Response::send()
        $response->expects($this->once())->method('send')->willReturnCallback(static function (bool $flush = true) use (&$flushed, $response) {
            $flushed = $flush;

            return $response;
        });
        $handledRequest = null;
        $application = $this->createMock(TestAppInterface::class);
        $application->expects($this->once())->method('handle')->willReturnCallback(static function (Request $request) use (&$handledRequest, $response) {
            $handledRequest = $request;

            return $response;
        });
        $termination = null;
        $application->expects($this->once())->method('terminate')->willReturnCallback(static function (Request $request, Response $terminatedResponse) use (&$termination, &$flushed) {
            $termination = [self::$handlingRequest, $flushed, $request, $terminatedResponse];
        });

        $server = $_SERVER;

        try {
            $this->assertSame(0, (new FrankenPhpWorkerRunner($application, 1))->run());
        } finally {
            $_SERVER = $server;
        }

        $this->assertSame([true, true, $handledRequest, $response], $termination);
        $this->assertFalse(self::$handlingRequest);
    }

    #[DataProvider('provideTerminationFailures')]
    public function testTerminationFailureIsRethrownAfterTheRequestCompletesAndStopsTheWorker(\Throwable $failure, int $requests, int $loopMax)
    {
        $application = $this->createMock(TestAppInterface::class);
        $application->expects($this->once())->method('handle')->willReturn(new Response());
        $application->expects($this->once())->method('terminate')->willThrowException($failure);
        self::$requests = $requests;
        $server = $_SERVER;

        try {
            (new FrankenPhpWorkerRunner($application, $loopMax))->run();
            $this->fail('The termination failure must be rethrown.');
        } catch (\LogicException|\Error $caught) {
            $this->assertSame($failure, $caught);
            $this->assertSame(1, self::$completedRequests);
        } finally {
            $_SERVER = $server;
        }
    }

    public static function provideTerminationFailures(): iterable
    {
        yield 'exception, next request pending' => [new \LogicException('Termination failed'), 2, 500];
        yield 'exception, request limit reached' => [new \LogicException('Termination failed'), 2, 1];
        yield 'error, next request pending' => [new \Error('Termination failed'), 2, 500];
        yield 'error, request limit reached' => [new \Error('Termination failed'), 2, 1];
    }

    public function testKernelTerminatesWhenSendingTheResponseFails()
    {
        $exception = new \LogicException('Sending failed');
        $response = $this->createMock(Response::class);
        $response->expects($this->once())->method('send')->willThrowException($exception);
        $application = $this->createMock(TestAppInterface::class);
        $application->expects($this->once())->method('handle')->willReturn($response);
        $termination = null;
        $application->expects($this->once())->method('terminate')->willReturnCallback(static function (Request $request, Response $terminatedResponse) use (&$termination) {
            $termination = [self::$handlingRequest, $terminatedResponse];
        });
        self::$reportedExceptions = [];
        $server = $_SERVER;

        try {
            $this->assertSame(0, (new FrankenPhpWorkerRunner($application, 1))->run());
            $this->assertSame([$exception], self::$reportedExceptions);
            $this->assertSame(0, self::$completedRequests);
        } finally {
            $_SERVER = $server;
        }

        $this->assertSame([true, $response], $termination);
        $this->assertFalse(self::$handlingRequest);
    }

    public function testRunHandlesTheNextRequestWhenSendingTheResponseFails()
    {
        $exception = new \LogicException('Sending failed');
        $failedResponse = $this->createMock(Response::class);
        $failedResponse->expects($this->once())->method('send')->willThrowException($exception);
        $application = $this->createMock(TestAppInterface::class);
        $application->expects($this->exactly(2))->method('handle')->willReturn($failedResponse, new Response());
        $application->expects($this->exactly(2))->method('terminate');
        self::$requests = 2;
        self::$reportedExceptions = [];
        $server = $_SERVER;

        try {
            $this->assertSame(0, (new FrankenPhpWorkerRunner($application, 500))->run());
            $this->assertSame([$exception], self::$reportedExceptions);
            $this->assertSame(1, self::$completedRequests);
        } finally {
            $_SERVER = $server;
        }
    }

    public function testKernelDoesNotTerminateWhenHandlingTheRequestFails()
    {
        $exception = new \LogicException('Handling failed');
        $handled = 0;
        $application = $this->createMock(TestAppInterface::class);
        $application->expects($this->exactly(2))->method('handle')->willReturnCallback(static function () use (&$handled, $exception): Response {
            if (1 === ++$handled) {
                throw $exception;
            }

            return new Response();
        });
        $application->expects($this->once())->method('terminate');
        self::$requests = 2;
        self::$reportedExceptions = [];
        $server = $_SERVER;

        try {
            $this->assertSame(0, (new FrankenPhpWorkerRunner($application, 500))->run());
            $this->assertSame([$exception], self::$reportedExceptions);
            $this->assertSame(1, self::$completedRequests);
        } finally {
            $_SERVER = $server;
        }
    }

    public function testTerminationErrorIsRethrownWhenSendingTheResponseAlsoFails()
    {
        $sendingException = new \LogicException('Sending failed');
        $terminationError = new \Error('Termination failed');
        $response = $this->createMock(Response::class);
        $response->expects($this->once())->method('send')->willThrowException($sendingException);
        $application = $this->createMock(TestAppInterface::class);
        $application->expects($this->once())->method('handle')->willReturn($response);
        $application->expects($this->once())->method('terminate')->willThrowException($terminationError);
        self::$requests = 2;
        self::$reportedExceptions = [];
        $server = $_SERVER;

        try {
            (new FrankenPhpWorkerRunner($application, 500))->run();
            $this->fail('The termination error must be rethrown.');
        } catch (\Error $caught) {
            $this->assertSame($terminationError, $caught);
            $this->assertSame([$sendingException], self::$reportedExceptions);
        } finally {
            $_SERVER = $server;
        }
    }

    public function testRunWithoutATerminableKernelStopsAtTheRequestLimit()
    {
        $application = $this->createMock(HttpKernelInterface::class);
        $application->expects($this->once())->method('handle')->willReturn(new Response());
        self::$requests = 2;
        $server = $_SERVER;

        try {
            $this->assertSame(0, (new FrankenPhpWorkerRunner($application, 1))->run());
            $this->assertSame(1, self::$requests);
        } finally {
            $_SERVER = $server;
        }
    }

    public function testRunDoesNotTerminateTheLastRequestAgainWhenTheWorkerStops()
    {
        $application = $this->createMock(TestAppInterface::class);
        $application->expects($this->exactly(2))->method('handle')->willReturn(new Response());
        $application->expects($this->exactly(2))->method('terminate');

        self::$requests = 2;

        $server = $_SERVER;

        try {
            $runner = new FrankenPhpWorkerRunner($application, 500);
            $this->assertSame(0, $runner->run());
            $this->assertSame(-1, self::$requests);
        } finally {
            $_SERVER = $server;
        }
    }

    public function testResponseIsReleasedBeforeTheNextRequest()
    {
        $previousResponse = null;
        $application = $this->createMock(HttpKernelInterface::class);
        $application->expects($this->exactly(2))->method('handle')->willReturnCallback(function () use (&$previousResponse) {
            $this->assertNull($previousResponse?->get());
            $response = new Response();
            $previousResponse = \WeakReference::create($response);

            return $response;
        });
        self::$requests = 2;
        $server = $_SERVER;

        try {
            $this->assertSame(0, (new FrankenPhpWorkerRunner($application, 500))->run());
        } finally {
            $_SERVER = $server;
        }
    }
}
