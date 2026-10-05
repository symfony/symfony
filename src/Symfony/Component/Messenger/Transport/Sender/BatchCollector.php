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
use Symfony\Component\Messenger\Stamp\BatchStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;

/**
 * Collects the messages of a batch while they go through the bus, then sends them.
 *
 * @author Joppe De Cuyper <hello@joppe.dev>
 *
 * @internal
 */
final class BatchCollector
{
    private bool $closed = false;

    /**
     * @var array<int, array{Envelope, array<SenderInterface>, ?EventDispatcherInterface}>
     */
    private array $pending = [];

    /**
     * @param array<SenderInterface> $senders
     */
    public function defer(int $index, Envelope $envelope, array $senders, ?EventDispatcherInterface $eventDispatcher): bool
    {
        if ($this->closed) {
            return false;
        }

        $this->pending[$index] = [$envelope, $senders, $eventDispatcher];

        return true;
    }

    public function close(): void
    {
        $this->closed = true;
    }

    /**
     * Sends the deferred messages, each sender getting all its messages at once.
     *
     * @param list<Envelope> $envelopes The envelopes returned by the bus
     *
     * @return list<Envelope>
     *
     * @throws BatchSendFailedException
     */
    public function flush(array $envelopes): array
    {
        $batches = [];

        foreach ($this->pending as $index => [$envelope, $senders]) {
            $envelopes[$index] = $envelope->withoutAll(BatchStamp::class);

            foreach ($senders as $alias => $sender) {
                $batches[$alias.'@'.spl_object_id($sender)] ??= [$sender, \is_string($alias) ? $alias : null, []];
                $batches[$alias.'@'.spl_object_id($sender)][2][] = $index;
            }
        }

        $exceptions = [];

        foreach ($batches as [$sender, $alias, $indexes]) {
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

        foreach ($this->pending as $index => [, $senders, $eventDispatcher]) {
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
