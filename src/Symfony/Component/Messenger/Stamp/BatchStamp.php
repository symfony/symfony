<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Stamp;

use Symfony\Component\Messenger\Transport\Sender\BatchCollector;

/**
 * Stamp identifying a message as part of a batch dispatch.
 *
 * This stamp can be used by event listeners to correlate messages
 * belonging to the same batch.
 *
 * @author Joppe De Cuyper <hello@joppe.dev>
 */
final class BatchStamp implements NonSendableStampInterface
{
    public function __construct(
        private string $batchId,
        private int $batchIndex,
        private int $batchSize,
        private ?BatchCollector $collector = null,
    ) {
    }

    public function getBatchId(): string
    {
        return $this->batchId;
    }

    public function getBatchIndex(): int
    {
        return $this->batchIndex;
    }

    public function getBatchSize(): int
    {
        return $this->batchSize;
    }

    /**
     * @internal
     */
    public function getCollector(): ?BatchCollector
    {
        return $this->collector;
    }
}
