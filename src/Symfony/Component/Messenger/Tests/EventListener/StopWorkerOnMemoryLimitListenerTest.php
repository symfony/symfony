<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMemoryLimitListener;
use Symfony\Component\Messenger\Worker;

class StopWorkerOnMemoryLimitListenerTest extends TestCase
{
    /**
     * @dataProvider memoryProvider
     */
    public function testWorkerStopsWhenMemoryLimitExceeded(int $memoryUsage, int $memoryLimit, bool $shouldStop)
    {
        $memoryResolver = fn () => $memoryUsage;

        $worker = $this->createMock(Worker::class);
        $worker->expects($shouldStop ? $this->once() : $this->never())->method('stop');
        $event = new WorkerRunningEvent($worker, false);

        $memoryLimitListener = new StopWorkerOnMemoryLimitListener($memoryLimit, null, $memoryResolver);
        $memoryLimitListener->onWorkerRunning($event);
    }

    public static function memoryProvider(): iterable
    {
        yield [2048, 1024, true];
        yield [1024, 1024, false];
        yield [1024, 2048, false];
    }

    public function testWorkerLogsMemoryExceededWhenLoggerIsGiven()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')
            ->with('Worker stopped due to memory limit of {limit} bytes exceeded ({memory} bytes used)', ['limit' => 64, 'memory' => 70]);

        $memoryResolver = fn () => 70;

        $worker = $this->createMock(Worker::class);
        $event = new WorkerRunningEvent($worker, false);

        $memoryLimitListener = new StopWorkerOnMemoryLimitListener(64, $logger, $memoryResolver);
        $memoryLimitListener->onWorkerRunning($event);
    }
}
