<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Event;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

/**
 * Event dispatched after a batch of messages has been sent to transports.
 *
 * @author Joppe De Cuyper <hello@joppe.dev>
 */
final class BatchSentToTransportsEvent
{
    /**
     * @param Envelope[]                     $envelopes
     * @param array<string, SenderInterface> $senders
     */
    public function __construct(
        private string $batchId,
        private array $envelopes,
        private array $senders,
    ) {
    }

    public function getBatchId(): string
    {
        return $this->batchId;
    }

    /**
     * @return Envelope[]
     */
    public function getEnvelopes(): array
    {
        return $this->envelopes;
    }

    /**
     * @return array<string, SenderInterface>
     */
    public function getSenders(): array
    {
        return $this->senders;
    }
}
