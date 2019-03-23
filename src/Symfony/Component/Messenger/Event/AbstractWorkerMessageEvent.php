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

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Messenger\Envelope;

/**
 * @experimental in 4.3
 */
abstract class AbstractWorkerMessageEvent extends Event
{
    private $envelope;
    private $receiverName;

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
}
