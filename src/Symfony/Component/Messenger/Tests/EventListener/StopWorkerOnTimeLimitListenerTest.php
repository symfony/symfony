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
use Symfony\Component\Messenger\EventListener\StopWorkerOnTimeLimitListener;
use Symfony\Component\Messenger\Worker;

class StopWorkerOnTimeLimitListenerTest extends TestCase
{
    /**
     * @group time-sensitive
     */
    public function testWorkerStopsWhenTimeLimitIsReached()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')
            ->with('Worker stopped due to time limit of {timeLimit}s exceeded', ['timeLimit' => 1]);

        $worker = $this->createMock(Worker::class);
        $worker->expects($this->once())->method('stop');
        $event = new WorkerRunningEvent($worker, false);

        $timeoutListener = new StopWorkerOnTimeLimitListener(1, $logger);
        $timeoutListener->onWorkerStarted();
        sleep(2);
        $timeoutListener->onWorkerRunning($event);
    }
}
