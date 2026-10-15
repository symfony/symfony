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
require_once __DIR__.'/Fixtures/gc-function-mock.php';

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\Runtime\Runner\FrankenPhpWorkerRunner;

interface TestAppInterface extends HttpKernelInterface, TerminableInterface
{
}

interface TestKernelInterface extends KernelInterface, TerminableInterface
{
}

class FrankenPhpWorkerRunnerTest extends TestCase
{
    public static ?array $gcStatus = null;
    public static int $gcCollections = 0;

    protected function tearDown(): void
    {
        unset($_SERVER['FRANKENPHP_RESET_KERNEL'], $_SERVER['APP_RUNTIME_MODE']);
        self::$gcStatus = null;
        self::$gcCollections = 0;
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

    public function testRunWithResponse()
    {
        $response = $this->createMock(Response::class);
        $response
            ->expects($this->once())
            ->method('send');

        $runner = new FrankenPhpWorkerRunner($response, 500);
        $this->assertSame(0, $runner->run());
    }

    public function testRunWithResetKernelTagsRuntimeMode()
    {
        $application = $this->createMock(TestAppInterface::class);
        $application
            ->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (Request $request): Response {
                $this->assertSame('web=1&worker=2', $request->server->get('APP_RUNTIME_MODE'));

                return new Response();
            });

        $_SERVER['FRANKENPHP_RESET_KERNEL'] = '1';

        $runner = new FrankenPhpWorkerRunner($application, 500);
        $this->assertSame(0, $runner->run());
    }

    public function testRunWithoutResetKernelTagsRuntimeMode()
    {
        $application = $this->createMock(TestAppInterface::class);
        $application
            ->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (Request $request): Response {
                $this->assertSame('web=1&worker=1', $request->server->get('APP_RUNTIME_MODE'));

                return new Response();
            });

        unset($_SERVER['FRANKENPHP_RESET_KERNEL']);

        $runner = new FrankenPhpWorkerRunner($application, 500);
        $this->assertSame(0, $runner->run());
    }

    public function testRunWithResponseIgnoresResetKernel()
    {
        $response = $this->createMock(Response::class);
        $response->expects($this->once())->method('send');

        $_SERVER['FRANKENPHP_RESET_KERNEL'] = '1';

        $runner = new FrankenPhpWorkerRunner($response, 500);
        $this->assertSame(0, $runner->run());
    }

    public function testRunWithZeroLoopMaxLoopsAtLeastOnce()
    {
        $application = $this->createMock(TestAppInterface::class);
        $application->expects($this->once())->method('handle')->willReturn(new Response());

        $runner = new FrankenPhpWorkerRunner($application, 0);
        $this->assertSame(0, $runner->run());
    }

    public function testRunWithNegativeLoopMaxLoopsAtLeastOnce()
    {
        $application = $this->createMock(TestAppInterface::class);
        $application->expects($this->once())->method('handle')->willReturn(new Response());

        $runner = new FrankenPhpWorkerRunner($application, -1);
        $this->assertSame(0, $runner->run());
    }

    public function testRunWithResetKernelShutsTheKernelDownAfterTerminatingIt()
    {
        $calls = [];
        $kernel = $this->createMock(TestKernelInterface::class);
        $kernel->expects($this->once())->method('handle')->willReturn(new Response());
        $kernel->expects($this->once())->method('terminate')->willReturnCallback(static function () use (&$calls) {
            $calls[] = 'terminate';
        });
        $kernel->expects($this->once())->method('shutdown')->willReturnCallback(static function () use (&$calls) {
            $calls[] = 'shutdown';
        });

        $_SERVER['FRANKENPHP_RESET_KERNEL'] = '1';
        self::$gcStatus = ['roots' => 0, 'threshold' => 10001];

        $runner = new FrankenPhpWorkerRunner($kernel, 500);
        $this->assertSame(0, $runner->run());
        $this->assertSame(['terminate', 'shutdown'], $calls);
        $this->assertSame(1, self::$gcCollections);
    }

    public function testRunDoesNotCollectCyclesBeforeTheRootBufferIsHalfFull()
    {
        $application = $this->createMock(TestAppInterface::class);
        $application->expects($this->once())->method('handle')->willReturn(new Response());

        self::$gcStatus = ['roots' => 5000, 'threshold' => 10001];

        $runner = new FrankenPhpWorkerRunner($application, 500);
        $this->assertSame(0, $runner->run());
        $this->assertSame(0, self::$gcCollections);
    }

    public function testRunCollectsCyclesWhenTheRootBufferIsHalfFull()
    {
        $application = $this->createMock(TestAppInterface::class);
        $application->expects($this->once())->method('handle')->willReturn(new Response());

        self::$gcStatus = ['roots' => 5001, 'threshold' => 10001];

        $runner = new FrankenPhpWorkerRunner($application, 500);
        $this->assertSame(0, $runner->run());
        $this->assertSame(1, self::$gcCollections);
    }
}
