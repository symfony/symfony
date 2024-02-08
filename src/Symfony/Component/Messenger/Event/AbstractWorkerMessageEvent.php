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
use Symfony\Component\Messenger\Stamp\StampInterface;

abstract class AbstractWorkerMessageEvent
{
    private Envelope $envelope;
    private string $receiverName;

    public function __construct(Envelope $envelope, string $receiverName)
    {
        $this->envelope = $envelope;
        $this->receiverName = $receiverName;
    }

    public function getEnvelope(): Envelope
    {
        return $this->envelope;
    }

    /**
     * Returns a unique identifier for transport receiver this message was received from.
     */
    public function getReceiverName(): string
    {
        return $this->receiverName;
    }

    public function addStamps(StampInterface ...$stamps): void
    {
        $this->envelope = $this->envelope->with(...$stamps);
    }
}
