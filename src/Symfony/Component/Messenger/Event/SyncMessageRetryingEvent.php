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
 * Dispatched right before the sync transport handles a failed message again.
 *
 * The event name is the class name.
 */
final class SyncMessageRetryingEvent
{
    public function __construct(
        private Envelope $envelope,
        private string $transportName,
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
}
