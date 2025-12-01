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
use Symfony\Component\Messenger\Exception\BatchSizeExceededException;
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
     *
     * @throws BatchSizeExceededException When a transport's max batch size is exceeded
     */
    public function flush(): array
    {
        $this->validateBatchSizes();

        $senders = $this->sendToTransports();

        $this->pending = [];
        ksort($this->envelopes);

        $this->dispatchEvents($senders);

        return $this->envelopes;
    }

    /**
     * @return array<string, SenderInterface> Map of alias to sender
     */
    private function sendToTransports(): array
    {
        $senders = [];

        foreach ($this->pending as $alias => $data) {
            $sender = $data['sender'];
            $senders[$alias] = $sender;

            $sent = $this->sendToTransport($alias, $sender, $data['items']);

            foreach ($sent as $index => $envelope) {
                $this->envelopes[$index] = $envelope;
            }
        }

        return $senders;
    }

    /**
     * @param list<array{envelope: Envelope, index: int}> $items
     *
     * @return array<int, Envelope> Map of original index to sent envelope
     */
    private function sendToTransport(string $alias, SenderInterface $sender, array $items): array
    {
        $envelopes = array_column($items, 'envelope');
        $indices = array_column($items, 'index');

        if ($sender instanceof BatchSenderInterface) {
            $sent = $sender->sendBatch($envelopes);
        } else {
            $this->logger?->warning('Transport "{alias}" does not implement BatchSenderInterface, falling back to individual sends.', [
                'alias' => $alias,
                'sender' => $sender::class,
            ]);
            $sent = array_map(static fn (Envelope $envelope) => $sender->send($envelope), $envelopes);
        }

        return array_combine($indices, $sent);
    }

    /**
     * @param array<string, SenderInterface> $senders
     */
    private function dispatchEvents(array $senders): void
    {
        if (null === $this->eventDispatcher || [] === $senders) {
            return;
        }

        foreach ($this->envelopes as $envelope) {
            $envelopeSenders = $this->getSendersForEnvelope($envelope, $senders);
            if ([] !== $envelopeSenders) {
                $this->eventDispatcher->dispatch(new MessageSentToTransportsEvent($envelope, $envelopeSenders));
            }
        }

        $this->eventDispatcher->dispatch(new BatchSentToTransportsEvent($this->batchId, $this->envelopes, $senders));
    }

    /**
     * Validates that no transport's max batch size is exceeded.
     *
     * @throws BatchSizeExceededException
     */
    private function validateBatchSizes(): void
    {
        foreach ($this->pending as $alias => $data) {
            $sender = $data['sender'];

            if (!$sender instanceof BatchSenderInterface) {
                continue;
            }

            $maxBatchSize = $sender->getMaxBatchSize();
            if (null === $maxBatchSize) {
                continue;
            }

            $batchSize = \count($data['items']);
            if ($batchSize > $maxBatchSize) {
                throw new BatchSizeExceededException($alias, $batchSize, $maxBatchSize);
            }
        }
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
