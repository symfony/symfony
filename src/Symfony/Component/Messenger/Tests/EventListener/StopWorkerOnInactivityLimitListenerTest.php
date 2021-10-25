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
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\EventListener\StopWorkerOnInactivityLimitListener;
use Symfony\Component\Messenger\Worker;

class StopWorkerOnInactivityLimitListenerTest extends TestCase
{
    /**
     * @group time-sensitive
     */
    public function testWorkerStopsWhenTimeLimitIsReached()
    {
        $worker = $this->createMock(Worker::class);
        $worker->expects($this->never())->method('stop');

        $timeoutListener = new StopWorkerOnInactivityLimitListener(1, new NullLogger());
        $timeoutListener->onWorkerStarted();
        sleep(2);
        $timeoutListener->onWorkerRunning(new WorkerRunningEvent($worker, false));

        $worker = $this->createMock(Worker::class);
        $worker->expects($this->once())->method('stop');
        sleep(2);
        $timeoutListener->onWorkerRunning(new WorkerRunningEvent($worker, true));
    }
}
