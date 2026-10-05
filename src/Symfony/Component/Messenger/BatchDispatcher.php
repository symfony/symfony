<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger;

use Symfony\Component\Messenger\Exception\BatchSendFailedException;
use Symfony\Component\Messenger\Stamp\BatchStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Transport\Sender\BatchCollector;

/**
 * Dispatches messages on a bus and sends them to their transports in batches.
 *
 * Each message goes through the middleware of the bus on its own, but sending it is deferred until all the messages went through.
 * Each sender then gets all its messages at once: the ones implementing BatchSenderInterface send them with as few requests as their transport allows, the others send them one by one.
 *
 * @author Joppe De Cuyper <hello@joppe.dev>
 */
final class BatchDispatcher
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    /**
     * When a message fails to go through the bus, its exception is thrown and none of the messages is sent.
     *
     * @param iterable<object|Envelope> $messages The messages or the messages pre-wrapped in envelopes
     * @param StampInterface[]          $stamps   Stamps added to every message
     *
     * @return list<Envelope> The envelopes, in the order of the messages
     *
     * @throws BatchSendFailedException When some messages could not be sent, to tell which ones were
     */
    public function dispatch(iterable $messages, array $stamps = []): array
    {
        $collector = new BatchCollector();
        $envelopes = [];

        try {
            foreach ($messages as $message) {
                $index = \count($envelopes);
                $envelopes[$index] = $this->bus->dispatch($message, [...$stamps, new BatchStamp($collector, $index)])->withoutAll(BatchStamp::class);
            }
        } finally {
            // a message that reaches its senders later, like one queued by DispatchAfterCurrentBusMiddleware, is sent on its own
            $collector->close();
        }

        return $collector->flush($envelopes);
    }
}
