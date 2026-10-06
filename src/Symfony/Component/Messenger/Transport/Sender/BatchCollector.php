<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Sender;

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\MessageSentToTransportsEvent;
use Symfony\Component\Messenger\Exception\BatchSendFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\BatchStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;

/**
 * Dispatches the messages of a batch, deferring their sending until the batch is flushed.
 *
 * @author Joppe De Cuyper <hello@joppe.dev>
 *
 * @internal
 */
final class BatchCollector implements MessageBusInterface
{
    private bool $closed = false;
    private int $nextId = 0;

    /**
     * @var array<int, Envelope>
     */
    private array $envelopes = [];

    /**
     * @var array<int, array{Envelope, array<SenderInterface>, ?EventDispatcherInterface}>
     */
    private array $pending = [];

    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        if ($this->closed) {
            return $this->bus->dispatch($message, $stamps);
        }

        $id = $this->nextId++;

        try {
            $envelope = $this->bus->dispatch($message, [...$stamps, new BatchStamp($this, $id)]);
        } catch (\Throwable $e) {
            unset($this->pending[$id]);

            throw $e;
        }

        return $this->envelopes[$id] = $envelope->withoutAll(BatchStamp::class);
    }

    /**
     * @param array<SenderInterface> $senders
     */
    public function defer(int $id, Envelope $envelope, array $senders, ?EventDispatcherInterface $eventDispatcher): bool
    {
        if ($this->closed) {
            return false;
        }

        $this->pending[$id] = [$envelope, $senders, $eventDispatcher];

        return true;
    }

    public function close(): void
    {
        $this->closed = true;
    }

    /**
     * Sends the deferred messages, each sender getting all its messages at once.
     *
     * @return list<Envelope> The envelopes, in the order the messages were dispatched
     *
     * @throws BatchSendFailedException
     */
    public function flush(): array
    {
        ksort($this->envelopes);
        $envelopes = $pending = $targets = $batches = [];
        $i = -1;

        // a message whose dispatch threw has an id but no envelope, so positions in the returned list are counted apart
        foreach ($this->envelopes as $id => $envelope) {
            $envelopes[++$i] = $envelope;

            if (!isset($this->pending[$id])) {
                continue;
            }

            [$envelope, $senders] = $pending[$i] = $this->pending[$id];
            $envelopes[$i] = $envelope->withoutAll(BatchStamp::class);

            foreach ($senders as $alias => $sender) {
                $targets[$target = $alias.'@'.spl_object_id($sender)] ??= [$sender, \is_string($alias) ? $alias : null];
                $batches[$target][] = $i;
            }
        }

        $exceptions = [];

        foreach ($batches as $target => $indexes) {
            [$sender, $alias] = $targets[$target];
            $batch = [];

            // like when sending a single message, a message that failed is not sent to its next transports
            foreach (array_diff($indexes, array_keys($exceptions)) as $index) {
                $batch[$index] = $envelopes[$index]->with(new SentStamp($sender::class, $alias));
            }

            if (!$batch) {
                continue;
            }

            try {
                $sent = $sender instanceof BatchSenderInterface ? $sender->sendBatch($batch) : self::sendOneByOne($sender, $batch);
            } catch (BatchSendFailedException $e) {
                $sent = $e->getEnvelopes();
                $exceptions += $e->getExceptions();
            } catch (\Throwable $e) {
                $sent = [];
                $exceptions += array_fill_keys(array_keys($batch), $e);
            }

            foreach ($sent as $index => $envelope) {
                $envelopes[$index] = $envelope;
            }
        }

        foreach ($pending as $index => [, $senders, $eventDispatcher]) {
            if (!isset($exceptions[$index])) {
                $eventDispatcher?->dispatch(new MessageSentToTransportsEvent($envelopes[$index], $senders));
            }
        }

        if ($exceptions) {
            ksort($exceptions);

            throw new BatchSendFailedException(array_diff_key($envelopes, $exceptions), $exceptions);
        }

        return $envelopes;
    }

    /**
     * @param non-empty-array<Envelope> $envelopes
     *
     * @return array<Envelope>
     */
    private static function sendOneByOne(SenderInterface $sender, array $envelopes): array
    {
        $sent = [];

        foreach ($envelopes as $index => $envelope) {
            try {
                $sent[$index] = $sender->send($envelope);
            } catch (\Throwable $e) {
                // the next messages are likely to fail the same way, like when the transport is down
                throw new BatchSendFailedException($sent, array_fill_keys(array_keys(array_diff_key($envelopes, $sent)), $e));
            }
        }

        return $sent;
    }
}
