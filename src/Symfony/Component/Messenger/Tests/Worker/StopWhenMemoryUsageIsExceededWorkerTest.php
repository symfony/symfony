<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Worker;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Tests\Fixtures\DummyWorker;
use Symfony\Component\Messenger\Worker\StopWhenMemoryUsageIsExceededWorker;

class StopWhenMemoryUsageIsExceededWorkerTest extends TestCase
{
    /**
     * @dataProvider memoryProvider
     */
    public function testWorkerStopsWhenMemoryLimitExceeded(int $memoryUsage, int $memoryLimit, bool $shouldStop)
    {
        $handlerCalledTimes = 0;
        $handledCallback = function () use (&$handlerCalledTimes) {
            ++$handlerCalledTimes;
        };
        $decoratedWorker = new DummyWorker([
            new Envelope(new \stdClass()),
        ]);

        $memoryResolver = function () use ($memoryUsage) {
            return $memoryUsage;
        };

        $memoryLimitWorker = new StopWhenMemoryUsageIsExceededWorker($decoratedWorker, $memoryLimit, null, $memoryResolver);
        $memoryLimitWorker->run([], $handledCallback);

        // handler should be called exactly 1 time
        $this->assertSame($handlerCalledTimes, 1);
        $this->assertSame($shouldStop, $decoratedWorker->isStopped());
    }

    public function memoryProvider(): iterable
    {
        yield [2048, 1024, true];
        yield [1024, 1024, false];
        yield [1024, 2048, false];
    }

    public function testWorkerLogsMemoryExceededWhenLoggerIsGiven()
    {
        $decoratedWorker = new DummyWorker([
            new Envelope(new \stdClass()),
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')
            ->with('Worker stopped due to memory limit of {limit} exceeded', ['limit' => 64 * 1024 * 1024]);

        $memoryResolver = function () {
            return 70 * 1024 * 1024;
        };

        $memoryLimitWorker = new StopWhenMemoryUsageIsExceededWorker($decoratedWorker, 64 * 1024 * 1024, $logger, $memoryResolver);
        $memoryLimitWorker->run();
    }
}
