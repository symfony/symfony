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
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyWorker;
use Symfony\Component\Messenger\Worker\StopWhenMessageCountIsExceededWorker;

class StopWhenMessageCountIsExceededWorkerTest extends TestCase
{
    /**
     * @dataProvider countProvider
     */
    public function testWorkerStopsWhenMaximumCountExceeded(int $max, bool $shouldStop)
    {
        $handlerCalledTimes = 0;
        $handledCallback = function () use (&$handlerCalledTimes) {
            ++$handlerCalledTimes;
        };
        // receive 3 real messages
        $decoratedWorker = new DummyWorker([
            new Envelope(new DummyMessage('First message')),
            null,
            new Envelope(new DummyMessage('Second message')),
            null,
            new Envelope(new DummyMessage('Third message')),
        ]);

        $maximumCountWorker = new StopWhenMessageCountIsExceededWorker($decoratedWorker, $max);
        $maximumCountWorker->run([], $handledCallback);

        $this->assertSame($shouldStop, $decoratedWorker->isStopped());
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
        $decoratedWorker = new DummyWorker([
            new Envelope(new \stdClass()),
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')
            ->with(
                $this->equalTo('Worker stopped due to maximum count of {count} exceeded'),
                $this->equalTo(['count' => 1])
            );

        $maximumCountWorker = new StopWhenMessageCountIsExceededWorker($decoratedWorker, 1, $logger);
        $maximumCountWorker->run();
    }
}
