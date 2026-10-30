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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Handler\Acknowledger;
use Symfony\Component\Messenger\Handler\BatchHandlerInterface;
use Symfony\Component\Messenger\Handler\BatchHandlerTrait;

class BatchHandlerTest extends TestCase
{
    public function testSynchronousAckReturnsTheResult()
    {
        $handler = new BatchHandlerTestHandler();

        $this->assertSame('the result', $handler(new \stdClass()));
    }

    public function testSynchronousNackRethrowsTheError()
    {
        $handler = new BatchHandlerTestHandler(new \RuntimeException('handler failed'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('handler failed');

        $handler(new \stdClass());
    }

    public function testSynchronousProcessFailureIsRethrown()
    {
        $handler = new BatchHandlerTestHandler(null, new \RuntimeException('process failed'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('process failed');

        $handler(new \stdClass());
    }

    public function testProcessFailureNacksTheJobsLeftInTheBatch()
    {
        $handler = new BatchHandlerTestHandler(null, new \RuntimeException('process failed'), 1);
        $results = [];

        foreach (['first', 'second'] as $job) {
            $handler(new \stdClass(), new Acknowledger('handler', static function (?\Throwable $e, $result) use (&$results, $job) {
                $results[$job] = $e ? $e->getMessage() : $result;
            }));
        }

        $error = null;
        try {
            $handler->flush(true);
        } catch (\RuntimeException $error) {
        }

        $this->assertSame('process failed', $error?->getMessage());
        $this->assertSame(['first' => 'the result', 'second' => 'process failed'], $results);
    }

    public function testFullBatchIsLeftToTheWorkerWhenTheAcknowledgerDefersTheFlush()
    {
        $handler = new BatchHandlerTestHandler(null, null, 0, 2);
        $deferred = 0;
        $deferFlush = static function () use (&$deferred): bool {
            ++$deferred;

            return true;
        };
        $first = new Acknowledger('handler', static function () {}, null, $deferFlush);
        $second = new Acknowledger('handler', static function () {}, null, $deferFlush);

        $this->assertSame(1, $handler(new \stdClass(), $first));
        $this->assertSame(2, $handler(new \stdClass(), $second));
        $this->assertSame(1, $deferred);
        $this->assertFalse($first->isAcknowledged());
        $this->assertFalse($second->isAcknowledged());

        $handler->flush(false);

        $this->assertTrue($first->isAcknowledged());
        $this->assertTrue($second->isAcknowledged());
    }

    public function testFullBatchIsFlushedRightAwayWhenTheAcknowledgerCannotDeferTheFlush()
    {
        $handler = new BatchHandlerTestHandler(null, null, 0, 1);
        $first = new Acknowledger('handler', static function () {});
        $second = new Acknowledger('handler', static function () {}, null, static fn () => false);

        $this->assertSame(0, $handler(new \stdClass(), $first));
        $this->assertSame(0, $handler(new \stdClass(), $second));
        $this->assertTrue($first->isAcknowledged());
        $this->assertTrue($second->isAcknowledged());
    }
}

class BatchHandlerTestHandler implements BatchHandlerInterface
{
    use BatchHandlerTrait;

    public function __construct(
        private ?\Throwable $error = null,
        private ?\Throwable $processError = null,
        private int $acksBeforeProcessError = 0,
        private int $batchSize = 10,
    ) {
    }

    public function __invoke(object $message, ?Acknowledger $ack = null): mixed
    {
        return $this->handle($message, $ack);
    }

    private function getBatchSize(): int
    {
        return $this->batchSize;
    }

    private function process(array $jobs): void
    {
        foreach ($jobs as $i => [, $ack]) {
            if ($this->processError && $i === $this->acksBeforeProcessError) {
                throw $this->processError;
            }

            if ($this->error) {
                $ack->nack($this->error);
            } else {
                $ack->ack('the result');
            }
        }
    }
}
