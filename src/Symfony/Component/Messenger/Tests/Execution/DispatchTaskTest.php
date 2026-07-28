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

use Amp\Cancellation;
use Amp\Parallel\Worker\ContextWorkerFactory;
use Amp\Sync\Channel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Execution\DispatchTask;
use Symfony\Component\Messenger\Execution\Message\DispatchEnvelopeMessage;
use Symfony\Component\Messenger\Execution\Message\FlushBatchHandlersMessage;
use Symfony\Component\Messenger\Execution\Message\HandledEnvelopeMessage;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Tests\Fixtures\App\HandledByBusStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Contracts\Service\ContainerProviderInterface;

class DispatchTaskTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(ContextWorkerFactory::class)) {
            $this->markTestSkipped(ContextWorkerFactory::class.' is not available.');
        }
    }

    public function testItBootstrapsTheApplicationUsingConsoleEntryPoint()
    {
        $envelope = $this->runTask(new Envelope(new DummyMessage('Hello')));

        $this->assertInstanceOf(Envelope::class, $envelope);
        $this->assertSame('Hello', $envelope->getMessage()->getMessage());
    }

    public function testItPreservesStampedBusRoutingWhenBootstrappingTheApplication()
    {
        $envelope = $this->runTask((new Envelope(new DummyMessage('Hello')))->with(new BusNameStamp('other.bus')));

        $this->assertSame('other.bus', $envelope->last(HandledByBusStamp::class)?->getBusName());
    }

    public function testFixtureConsoleReturnsAContainerAwareApplicationDirectly()
    {
        $app = (static fn () => require __DIR__.'/../Fixtures/App/bin/console')->bindTo(null, null)();

        $this->assertInstanceOf(ContainerProviderInterface::class, $app);
    }

    private function runTask(Envelope $envelope): Envelope
    {
        $result = null;

        $channel = new class($envelope, $result) implements Channel {
            private int $received = 0;

            public function __construct(
                private readonly Envelope $envelope,
                private mixed &$result,
            ) {
            }

            public function send(mixed $data): void
            {
                $this->result = $data;
            }

            public function receive(?Cancellation $cancellation = null): mixed
            {
                return match ($this->received++) {
                    0 => new DispatchEnvelopeMessage(1, $this->envelope),
                    1 => new FlushBatchHandlersMessage(true),
                    default => null,
                };
            }

            public function close(): void
            {
            }

            public function isClosed(): bool
            {
                return false;
            }

            public function onClose(\Closure $onClose): void
            {
            }
        };

        $task = new DispatchTask(__DIR__.'/../Fixtures/App/bin/console');

        $cancellation = new class implements Cancellation {
            public function subscribe(\Closure $callback): string
            {
                return 'subscription';
            }

            public function unsubscribe(string $id): void
            {
            }

            public function throwIfRequested(): void
            {
            }

            public function isRequested(): bool
            {
                return false;
            }
        };

        $task->run($channel, $cancellation);

        self::assertInstanceOf(HandledEnvelopeMessage::class, $result);

        return $result->envelope;
    }
}
