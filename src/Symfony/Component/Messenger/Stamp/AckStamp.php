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

use Symfony\Component\Messenger\Envelope;

/**
 * Marker stamp for messages that can be ack/nack'ed.
 */
final class AckStamp implements NonSendableStampInterface
{
    private $ack;

    /**
     * @param \Closure(Envelope, \Throwable|null) $ack
     */
    public function __construct(\Closure $ack)
    {
        $this->ack = $ack;
    }

    public function ack(Envelope $envelope, ?\Throwable $e = null): void
    {
        ($this->ack)($envelope, $e);
    }
}
