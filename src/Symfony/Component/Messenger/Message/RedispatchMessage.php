<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Message;

use Symfony\Component\Messenger\Envelope;

final class RedispatchMessage implements \Stringable
{
    /**
     * @param object|Envelope $envelope       The message or the message pre-wrapped in an envelope
     * @param string[]|string $transportNames Transport names to be used for the message
     */
    public function __construct(
        public readonly object $envelope,
        public readonly array|string $transportNames = [],
    ) {
    }

    public function __toString(): string
    {
        $message = $this->envelope instanceof Envelope ? $this->envelope->getMessage() : $this->envelope;

        return \sprintf('%s via %s', $message instanceof \Stringable ? (string) $message : $message::class, implode(', ', (array) $this->transportNames));
    }
}
