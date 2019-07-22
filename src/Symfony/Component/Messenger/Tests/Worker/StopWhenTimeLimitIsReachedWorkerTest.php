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
use Symfony\Component\Messenger\Worker\StopWhenTimeLimitIsReachedWorker;

class StopWhenTimeLimitIsReachedWorkerTest extends TestCase
{
    /**
     * @group time-sensitive
     */
    public function testWorkerStopsWhenTimeLimitIsReached()
    {
        $decoratedWorker = new DummyWorker([
            new Envelope(new \stdClass()),
            new Envelope(new \stdClass()),
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')
            ->with('Worker stopped due to time limit of {timeLimit}s reached', ['timeLimit' => 1]);

        $timeoutWorker = new StopWhenTimeLimitIsReachedWorker($decoratedWorker, 1, $logger);
        $timeoutWorker->run([], function () {
            sleep(2);
        });

        $this->assertTrue($decoratedWorker->isStopped());
        $this->assertSame(1, $decoratedWorker->countEnvelopesHandled());
    }
}
