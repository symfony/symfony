<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Execution;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Execution\DeferredBatchMessageQueue;
use Symfony\Component\Messenger\Execution\Message\DeferredBatchMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

class DeferredBatchMessageQueueTest extends TestCase
{
    public function testItReturnsAllEntriesWhenForceFlushing()
    {
        $queue = new DeferredBatchMessageQueue();
        $batchHandler = new \stdClass();
        $acked = false;
        $envelope = new Envelope(new DummyMessage('Hello'));

        $queue->add($batchHandler, 7, $envelope, $acked, 10.0);

        $flushable = $queue->popFlushable(true, 10.0);

        $this->assertCount(1, $flushable);
        $this->assertFalse($queue->hasPending());
        $this->assertEquals(new DeferredBatchMessage(7, $envelope, $acked, 10.0), $flushable[$batchHandler]);
    }

    public function testItKeepsRecentEntriesWhenFlushingByIdleTimeout()
    {
        $queue = new DeferredBatchMessageQueue();
        $readyHandler = new \stdClass();
        $waitingHandler = new \stdClass();
        $readyAcked = false;
        $waitingAcked = false;
        $readyEnvelope = new Envelope(new DummyMessage('ready'));
        $waitingEnvelope = new Envelope(new DummyMessage('waiting'));

        $queue->add($readyHandler, 1, $readyEnvelope, $readyAcked, 10.0);
        $queue->add($waitingHandler, 2, $waitingEnvelope, $waitingAcked, 19.5);

        $flushable = $queue->popFlushable(5.0, 20.0);

        $this->assertCount(1, $flushable);
        $this->assertTrue($queue->hasPending());
        $this->assertEquals(new DeferredBatchMessage(1, $readyEnvelope, $readyAcked, 10.0), $flushable[$readyHandler]);

        $remaining = $queue->popFlushable(true, 20.0);

        $this->assertCount(1, $remaining);
        $this->assertFalse($queue->hasPending());
        $this->assertEquals(new DeferredBatchMessage(2, $waitingEnvelope, $waitingAcked, 19.5), $remaining[$waitingHandler]);
    }
}
