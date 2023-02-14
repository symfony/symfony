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
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\EventListener\StopWorkerOnCustomStopExceptionListener;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\StopWorkerException;
use Symfony\Component\Messenger\Exception\StopWorkerExceptionInterface;
use Symfony\Component\Messenger\Worker;

class StopWorkerOnCustomStopExceptionListenerTest extends TestCase
{
    public static function provideTests(): \Generator
    {
        yield 'it should not stop (1)' => [new \Exception(), false];
        yield 'it should not stop (2)' => [new HandlerFailedException(new Envelope(new \stdClass()), [new \Exception()]), false];

        $t = new class() extends \Exception implements StopWorkerExceptionInterface {};
        yield 'it should stop with custom exception' => [$t, true];
        yield 'it should stop with core exception' => [new StopWorkerException(), true];

        yield 'it should stop with custom exception wrapped (1)' => [new HandlerFailedException(new Envelope(new \stdClass()), [new StopWorkerException()]), true];
        yield 'it should stop with custom exception wrapped (2)' => [new HandlerFailedException(new Envelope(new \stdClass()), [new \Exception(), new StopWorkerException()]), true];
        yield 'it should stop with core exception wrapped (1)' => [new HandlerFailedException(new Envelope(new \stdClass()), [$t]), true];
        yield 'it should stop with core exception wrapped (2)' => [new HandlerFailedException(new Envelope(new \stdClass()), [new \Exception(), $t]), true];
    }

    /** @dataProvider provideTests */
    public function test(\Throwable $throwable, bool $shouldStop)
    {
        $listener = new StopWorkerOnCustomStopExceptionListener();

        $envelope = new Envelope(new \stdClass());
        $failedEvent = new WorkerMessageFailedEvent($envelope, 'my_receiver', $throwable);

        $listener->onMessageFailed($failedEvent);

        $worker = $this->createMock(Worker::class);
        $worker->expects($shouldStop ? $this->once() : $this->never())->method('stop');
        $runningEvent = new WorkerRunningEvent($worker, false);

        $listener->onWorkerRunning($runningEvent);
    }
}
