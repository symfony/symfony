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
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\Worker;

class StopWorkerOnMessageLimitListenerTest extends TestCase
{
    /**
     * @dataProvider countProvider
     */
    public function testWorkerStopsWhenMaximumCountExceeded(int $max, bool $shouldStop)
    {
        $worker = self::createMock(Worker::class);
        $worker->expects($shouldStop ? self::atLeastOnce() : self::never())->method('stop');
        $event = new WorkerRunningEvent($worker, false);

        $maximumCountListener = new StopWorkerOnMessageLimitListener($max);
        // simulate three messages processed
        $maximumCountListener->onWorkerRunning($event);
        $maximumCountListener->onWorkerRunning($event);
        $maximumCountListener->onWorkerRunning($event);
    }

    public function countProvider(): iterable
    {
        yield [1, true];
        yield [2, true];
        yield [3, true];
        yield [4, false];
    }

    public function testWorkerLogsMaximumCountExceededWhenLoggerIsGiven()
    {
        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with(
                self::equalTo('Worker stopped due to maximum count of {count} messages processed'),
                self::equalTo(['count' => 1])
            );

        $worker = self::createMock(Worker::class);
        $event = new WorkerRunningEvent($worker, false);

        $maximumCountListener = new StopWorkerOnMessageLimitListener(1, $logger);
        $maximumCountListener->onWorkerRunning($event);
    }
}
