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

    public function testItFlushesWithAMessageThatIsStillPendingWhenTheLastOneReportedWasAcked()
    {
        $queue = new DeferredBatchMessageQueue();
        $batchHandler = new \stdClass();
        $pendingAcked = false;
        $flusherAcked = false;
        $pending = new Envelope(new DummyMessage('pending'));
        $flusher = new Envelope(new DummyMessage('flusher'));

        // the message that flushed the batch is reported after the ones that came next, and its flush acked it
        $queue->add($batchHandler, 1, $pending, $pendingAcked, 10.0);
        $queue->add($batchHandler, 1, $flusher, $flusherAcked, 11.0);
        $flusherAcked = true;

        $flushable = $queue->popFlushable(true, 12.0);

        $this->assertCount(1, $flushable);
        $this->assertSame($pending, $flushable[$batchHandler]->envelope);
        $this->assertFalse($queue->hasPending());
    }

    public function testItFlushesWithTheLastMessageWhenAllOfThemAreAcked()
    {
        $queue = new DeferredBatchMessageQueue();
        $batchHandler = new \stdClass();
        $firstAcked = false;
        $lastAcked = false;
        $last = new Envelope(new DummyMessage('last'));

        $queue->add($batchHandler, 1, new Envelope(new DummyMessage('first')), $firstAcked, 10.0);
        $queue->add($batchHandler, 1, $last, $lastAcked, 11.0);
        $firstAcked = $lastAcked = true;

        $this->assertSame($last, $queue->popFlushable(true, 12.0)[$batchHandler]->envelope);
    }

    public function testItForgetsTheMessagesThatGotTheirOutcomeWhenAddingAnother()
    {
        $queue = new DeferredBatchMessageQueue();
        $batchHandler = new \stdClass();
        $firstAcked = false;
        $secondAcked = false;
        $second = new Envelope(new DummyMessage('second'));

        $queue->add($batchHandler, 1, new Envelope(new DummyMessage('first')), $firstAcked, 10.0);
        $firstAcked = true;
        $queue->add($batchHandler, 1, $second, $secondAcked, 11.0);

        $this->assertSame($second, $queue->popFlushable(true, 12.0)[$batchHandler]->envelope);
    }

    public function testItKeepsTheEntryOfAHandlerUntilItsLastMessageWaitedLongEnough()
    {
        $queue = new DeferredBatchMessageQueue();
        $batchHandler = new \stdClass();
        $firstAcked = false;
        $lastAcked = false;

        $queue->add($batchHandler, 1, new Envelope(new DummyMessage('first')), $firstAcked, 10.0);
        $queue->add($batchHandler, 1, new Envelope(new DummyMessage('last')), $lastAcked, 19.5);

        $this->assertCount(0, $queue->popFlushable(5.0, 20.0));
        $this->assertTrue($queue->hasPending());
        $this->assertCount(1, $queue->popFlushable(5.0, 25.0));
    }
}
