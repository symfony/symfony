<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Messenger;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Scheduler\Exception\LogicException;
use Symfony\Component\Scheduler\Generator\MessageGeneratorInterface;

/**
 * @experimental
 */
class SchedulerTransport implements TransportInterface
{
    public function __construct(
        private readonly MessageGeneratorInterface $messageGenerator,
        private readonly ?TransportInterface $retryTransport = null,
    ) {
    }

    public function get(): iterable
    {
        // Every other type of transports should NOT be consumed here but by a
        // dedicated consumer
        if ($this->retryTransport instanceof InMemoryTransport) {
            yield from $this->retryTransport->get();
        }

        foreach ($this->messageGenerator->getMessages() as $message) {
            yield Envelope::wrap($message, [new ScheduledStamp()]);
        }
    }

    public function ack(Envelope $envelope): void
    {
        if ($this->retryTransport && $envelope->last(ScheduledStamp::class) && $envelope->last(RedeliveryStamp::class)) {
            $this->retryTransport->ack($envelope);
        }

        // ignore
    }

    public function reject(Envelope $envelope): void
    {
        if ($this->retryTransport && $envelope->last(ScheduledStamp::class) && $envelope->last(RedeliveryStamp::class)) {
            $this->retryTransport->reject($envelope);
        }

        // ignore
    }

    public function send(Envelope $envelope): Envelope
    {
        if ($envelope->last(ScheduledStamp::class) && $envelope->last(RedeliveryStamp::class)) {
            if (!$this->retryTransport) {
                throw new LogicException(sprintf('"%s" is not configured for retry. Please enable it by specifying ?retry=anotherTransport to its DSN', __CLASS__));
            }

            return $this->retryTransport->send($envelope);
        }

        throw new LogicException(sprintf('"%s" cannot send messages.', __CLASS__));
    }
}
