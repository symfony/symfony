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
use Symfony\Component\Messenger\Event\WorkerBusyEvent;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\Worker;

class StopWorkerOnMessageLimitListenerTest extends TestCase
{
    /**
     * @dataProvider countProvider
     */
    public function testWorkerStopsWhenMaximumCountExceeded(int $max, bool $shouldStop)
    {
        $worker = $this->createMock(Worker::class);
        $worker->expects($shouldStop ? $this->atLeastOnce() : $this->never())->method('stop');
        $event = new WorkerBusyEvent($worker);

        $maximumCountListener = new StopWorkerOnMessageLimitListener($max);
        // simulate three messages processed
        $maximumCountListener->onWorkerBusy($event);
        $maximumCountListener->onWorkerBusy($event);
        $maximumCountListener->onWorkerBusy($event);
    }

    public static function countProvider(): iterable
    {
        yield [1, true];
        yield [2, true];
        yield [3, true];
        yield [4, false];
    }

    public function testWorkerLogsMaximumCountExceededWhenLoggerIsGiven()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')
            ->with(
                $this->equalTo('Worker stopped due to maximum count of {count} messages processed'),
                $this->equalTo(['count' => 1])
            );

        $worker = $this->createMock(Worker::class);
        $event = new WorkerBusyEvent($worker);

        $maximumCountListener = new StopWorkerOnMessageLimitListener(1, $logger);
        $maximumCountListener->onWorkerBusy($event);
    }
}
