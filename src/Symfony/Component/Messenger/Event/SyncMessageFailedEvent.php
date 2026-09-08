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

/**
 * Dispatched when handling a message through the sync transport failed,
 * before the message is retried, sent to the failure transport or rethrown.
 *
 * The event name is the class name.
 */
final class SyncMessageFailedEvent
{
    public function __construct(
        private Envelope $envelope,
        private string $transportName,
        private \Throwable $throwable,
        private bool $willRetry,
    ) {
    }

    public function getEnvelope(): Envelope
    {
        return $this->envelope;
    }

    public function getTransportName(): string
    {
        return $this->transportName;
    }

    public function getThrowable(): \Throwable
    {
        return $this->throwable;
    }

    public function willRetry(): bool
    {
        return $this->willRetry;
    }
}
