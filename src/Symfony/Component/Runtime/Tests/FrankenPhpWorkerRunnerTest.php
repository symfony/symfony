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
    public static ?int $requests = null;
    public static bool $handlingRequest = false;
    public static int $completedRequests = 0;

    protected function tearDown(): void
    {
        self::$requests = null;
        self::$completedRequests = 0;
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
        $sent = false;
        $response->expects($this->once())->method('send')->willReturnCallback(static function () use (&$sent, $response) {
            $sent = true;

            return $response;
        });
        $handledRequest = null;
        $application = $this->createMock(TestAppInterface::class);
        $application->expects($this->once())->method('handle')->willReturnCallback(static function (Request $request) use (&$handledRequest, $response) {
            $handledRequest = $request;

            return $response;
        });
        $application->expects($this->once())->method('terminate')->willReturnCallback(function (Request $request, Response $terminatedResponse) use (&$handledRequest, $response, &$sent) {
            $this->assertTrue(self::$handlingRequest);
            $this->assertTrue($sent);
            $this->assertSame($handledRequest, $request);
            $this->assertSame($response, $terminatedResponse);
        });

        $server = $_SERVER;

        try {
            $this->assertSame(0, (new FrankenPhpWorkerRunner($application, 1))->run());
        } finally {
            $_SERVER = $server;
        }
    }

    public function testTerminationExceptionIsRethrownAfterTheRequestCompletes()
    {
        $exception = new \RuntimeException('Termination failed');
        $application = $this->createMock(TestAppInterface::class);
        $application->expects($this->once())->method('handle')->willReturn(new Response());
        $application->expects($this->once())->method('terminate')->willThrowException($exception);
        $server = $_SERVER;

        try {
            (new FrankenPhpWorkerRunner($application, 1))->run();
            $this->fail('The termination exception must be rethrown.');
        } catch (\RuntimeException $caught) {
            $this->assertSame($exception, $caught);
            $this->assertSame(1, self::$completedRequests);
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
        } finally {
            $_SERVER = $server;
        }
    }
}
