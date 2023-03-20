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

/**
 * @internal
 */
final class RedispatchMessage
{
    /**
     * @param object|Envelope $message        The message or the message pre-wrapped in an envelope
     * @param string[]|string $transportNames Transport names to be used for the message
     */
    public function __construct(
        public readonly object $envelope,
        public readonly array|string $transportNames = [],
    ) {
    }
}
