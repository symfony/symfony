<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Handler;

use Amp\Future;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\Acknowledger;
use Symfony\Component\Messenger\Handler\BatchAsyncHandlerTrait;
use Symfony\Component\Messenger\ParallelMessageBus;
use Symfony\Component\Messenger\Stamp\FutureStamp;

class BatchAsyncHandlerTraitTest extends TestCase
{
    public function testHandleWillProcessMessagesAfterReachingBatchSize()
    {
        $handler = new TestBatchAsyncHandler();
        $handler->setBatchSize(2);

        $message1 = new \stdClass();
        $message2 = new \stdClass();
        $message3 = new \stdClass();

        $result1 = $handler->handle($message1, new Acknowledger('TestBatchAsyncHandler'));
        $this->assertSame(1, $result1);
        $this->assertEmpty($handler->getProcessedJobs());

        $result2 = $handler->handle($message2, new Acknowledger('TestBatchAsyncHandler'));
        $this->assertSame(0, $result2);
        $this->assertCount(2, $handler->getProcessedJobs()[0]);

        $result3 = $handler->handle($message3, new Acknowledger('TestBatchAsyncHandler'));
        $this->assertSame(1, $result3);
        $this->assertCount(2, $handler->getProcessedJobs()[0]);
    }

    public function testHandleWithNullAcknowledgerProcessesImmediately()
    {
        $handler = new TestBatchAsyncHandler();
        $handler->setBatchSize(5);

        $message = new \stdClass();
        $message->payload = 'test';

        $result = $handler->handle($message, null);
        $this->assertSame('processed:test', $result);
        $this->assertCount(1, $handler->getProcessedJobs()[0]);
    }

    public function testFlushWithForceTrueProcessesRegardlessOfBatchSize()
    {
        $handler = new TestBatchAsyncHandler();
        $handler->setBatchSize(5);

        $message1 = new \stdClass();
        $message2 = new \stdClass();

        $handler->handle($message1, new Acknowledger('TestBatchAsyncHandler'));
        $handler->handle($message2, new Acknowledger('TestBatchAsyncHandler'));

        $this->assertCount(0, $handler->getProcessedJobs());

        $handler->flush(true);
        $this->assertCount(2, $handler->getProcessedJobs()[0]);
    }

    public function testParallelDispatch()
    {
        $message1 = new \stdClass();
        $message1->payload = 'test1';

        $message2 = new \stdClass();
        $message2->payload = 'test2';

        $future1 = $this->createMock(Future::class);
        $future1->expects($this->once())
            ->method('await')
            ->willReturn('future_result1');

        $future2 = $this->createMock(Future::class);
        $future2->expects($this->once())
            ->method('await')
            ->willReturn('future_result2');

        $futureStamp1 = new FutureStamp($future1);
        $futureStamp2 = new FutureStamp($future2);

        $envelope1 = new Envelope($message1, [$futureStamp1]);
        $envelope2 = new Envelope($message2, [$futureStamp2]);

        $parallelBus = $this->createMock(ParallelMessageBus::class);
        $parallelBus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnOnConsecutiveCalls($envelope1, $envelope2);

        $handler = new TestBatchAsyncHandler();
        $handler->setBatchSize(2);
        $handler->setParallelMessageBus($parallelBus);

        $ack1 = $this->createMock(Acknowledger::class);
        $ack1->expects($this->once())
            ->method('ack')
            ->with('future_result1');

        $ack2 = $this->createMock(Acknowledger::class);
        $ack2->expects($this->once())
            ->method('ack')
            ->with('future_result2');

        $handler->handle($message1, $ack1);
        $handler->handle($message2, $ack2);

        // Jobs should have been dispatched via the parallel bus
        $this->assertEmpty($handler->getProcessedJobs());
    }

    public function testFallbackToSyncProcessingWhenNoBusAvailable()
    {
        $handler = new TestBatchAsyncHandler();
        $handler->setBatchSize(2);

        $message1 = new \stdClass();
        $message2 = new \stdClass();

        $handler->handle($message1, new Acknowledger('TestBatchAsyncHandler'));
        $handler->handle($message2, new Acknowledger('TestBatchAsyncHandler'));

        $this->assertCount(2, $handler->getProcessedJobs()[0]);
    }

    public function testParallelDispatchWithException()
    {
        $message = new \stdClass();
        $message->payload = 'test_exception';

        $future = $this->createMock(Future::class);
        $future->expects($this->once())
            ->method('await')
            ->willThrowException(new \RuntimeException('Test exception'));

        $futureStamp = new FutureStamp($future);
        $envelope = new Envelope($message, [$futureStamp]);

        $parallelBus = $this->createMock(ParallelMessageBus::class);
        $parallelBus->expects($this->once())
            ->method('dispatch')
            ->willReturn($envelope);

        $handler = new TestBatchAsyncHandler();
        $handler->setParallelMessageBus($parallelBus);

        $ack = $this->createMock(Acknowledger::class);
        $ack->expects($this->once())
            ->method('nack')
            ->with($this->isInstanceOf(\RuntimeException::class));

        $handler->handle($message, $ack);
        $handler->flush(true);
    }

    public function testCleanupWorker()
    {
        $handler = new TestBatchAsyncHandler();

        $message = new \stdClass();
        $handler->handle($message, new Acknowledger('TestBatchAsyncHandler'));

        $this->assertEmpty($handler->getProcessedJobs());

        $handler->cleanupWorker();

        $this->assertCount(1, $handler->getProcessedJobs()[0]);
    }
}

/**
 * Test implementation of BatchAsyncHandlerTrait.
 */
class TestBatchAsyncHandler
{
    use BatchAsyncHandlerTrait {
        BatchAsyncHandlerTrait::getCurrentWorkerId as private traitGetCurrentWorkerId;
    }

    private int $batchSize = 10;
    private array $processedJobs = [];

    public function handle(object $message, ?Acknowledger $ack): mixed
    {
        return $this->{'handle'}($message, $ack);
    }

    private function process(array $jobs): void
    {
        $this->processedJobs[] = $jobs;

        foreach ($jobs as [$message, $ack]) {
            if (property_exists($message, 'payload')) {
                $result = 'processed:' . $message->payload;
                $ack->ack($result);
            } else {
                $ack->ack(true);
            }
        }
    }

    public function setBatchSize(int $size): void
    {
        $this->batchSize = $size;
    }

    public function getProcessedJobs(): array
    {
        return $this->processedJobs;
    }

    private function getBatchSize(): int
    {
        return $this->batchSize;
    }

    private function getCurrentWorkerId(): string
    {
        return 'test-worker-id';
    }
}
