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

    protected function tearDown(): void
    {
        self::$requests = null;
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

    public function testRunDoesNotTerminateTheLastRequestAgainWhenTheWorkerStops()
    {
        $application = $this->createMock(TestAppInterface::class);
        $application->expects($this->exactly(2))->method('handle')->willReturn(new Response());
        $application->expects($this->exactly(2))->method('terminate');

        self::$requests = 2;

        $runner = new FrankenPhpWorkerRunner($application, 500);
        $this->assertSame(0, $runner->run());
    }
}
