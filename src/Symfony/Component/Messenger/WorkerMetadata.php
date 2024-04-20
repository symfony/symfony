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

/**
 * @author Oleg Krasavin <okwinza@gmail.com>
 */
final class WorkerMetadata
{
    private array $metadata;

    public function __construct(array $metadata)
    {
        $this->metadata = $metadata;
    }

    public function set(array $newMetadata): void
    {
        $this->metadata = array_merge($this->metadata, $newMetadata);
    }

    /**
     * Returns the queue names the worker consumes from, if "--queues" option was used.
     * Returns null otherwise.
     */
    public function getQueueNames(): ?array
    {
        return $this->metadata['queueNames'] ?? null;
    }

    /**
     * Returns an array of unique identifiers for transport receivers the worker consumes from.
     */
    public function getTransportNames(): array
    {
        return $this->metadata['transportNames'] ?? [];
    }
}
