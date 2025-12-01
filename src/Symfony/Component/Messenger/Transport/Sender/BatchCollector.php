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
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\BatchSentToTransportsEvent;
use Symfony\Component\Messenger\Event\MessageSentToTransportsEvent;
use Symfony\Component\Messenger\Stamp\SentStamp;

/**
 * Collects envelopes during batch dispatch and flushes them to transports.
 *
 * @author Joppe De Cuyper <hello@joppe.dev>
 *
 * @internal
 */
final class BatchCollector
{
    use LoggerAwareTrait;

    private string $batchId;

    /** @var array<int, Envelope> */
    private array $envelopes = [];

    /** @var array<string, array{sender: SenderInterface, items: list<array{envelope: Envelope, index: int}>}> */
    private array $pending = [];

    public function __construct(
        private ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        $this->batchId = bin2hex(random_bytes(16));
    }

    public function getBatchId(): string
    {
        return $this->batchId;
    }

    /**
     * Track an envelope after it has passed through the middleware chain.
     */
    public function trackEnvelope(int $index, Envelope $envelope): void
    {
        $this->envelopes[$index] = $envelope;
    }

    /**
     * Add an envelope to be sent to a specific transport.
     */
    public function addPendingSend(string $alias, SenderInterface $sender, Envelope $envelope, int $index): void
    {
        $this->pending[$alias] ??= ['sender' => $sender, 'items' => []];
        $this->pending[$alias]['items'][] = ['envelope' => $envelope, 'index' => $index];
    }

    /**
     * Flush all pending sends and return the final envelopes.
     *
     * @return Envelope[]
     */
    public function flush(): array
    {
        /** @var array<string, SenderInterface> $senders */
        $senders = [];

        foreach ($this->pending as $alias => $data) {
            $sender = $data['sender'];
            $senders[$alias] = $sender;
            $envelopes = array_column($data['items'], 'envelope');
            $indices = array_column($data['items'], 'index');

            if ($sender instanceof BatchSenderInterface) {
                $sent = $sender->sendBatch($envelopes);
            } else {
                $this->logger?->warning('Transport "{alias}" does not implement BatchSenderInterface, falling back to individual sends.', [
                    'alias' => $alias,
                    'sender' => $sender::class,
                ]);
                $sent = array_map(fn (Envelope $envelope) => $sender->send($envelope), $envelopes);
            }

            // Update tracked envelopes with results from transport
            foreach ($sent as $i => $envelope) {
                $this->envelopes[$indices[$i]] = $envelope;
            }
        }

        $this->pending = [];

        // Sort by index
        ksort($this->envelopes);

        // Dispatch events
        if (null !== $this->eventDispatcher && [] !== $senders) {
            foreach ($this->envelopes as $envelope) {
                $envelopeSenders = $this->getSendersForEnvelope($envelope, $senders);
                if ([] !== $envelopeSenders) {
                    $this->eventDispatcher->dispatch(new MessageSentToTransportsEvent($envelope, $envelopeSenders));
                }
            }

            $this->eventDispatcher->dispatch(new BatchSentToTransportsEvent($this->batchId, $this->envelopes, $senders));
        }

        return $this->envelopes;
    }

    /**
     * @param array<string, SenderInterface> $senders
     *
     * @return array<string, SenderInterface>
     */
    private function getSendersForEnvelope(Envelope $envelope, array $senders): array
    {
        $envelopeSenders = [];
        foreach ($envelope->all(SentStamp::class) as $sentStamp) {
            $alias = $sentStamp->getSenderAlias();
            if (null !== $alias && isset($senders[$alias])) {
                $envelopeSenders[$alias] = $senders[$alias];
            }
        }

        return $envelopeSenders;
    }
}
