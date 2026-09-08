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
use Symfony\Component\Messenger\EventListener\DispatchOnFailureListener;
use Symfony\Component\Messenger\Middleware\DispatchOnFailureMiddleware;

/**
 * Names the message to dispatch when the current message fails for good.
 *
 * The failure message is dispatched with a FailedMessageStamp holding the
 * message that failed, and an ErrorDetailsStamp describing the error.
 *
 * @see DispatchOnFailureListener
 * @see DispatchOnFailureMiddleware
 */
final class DispatchOnFailureStamp implements StampInterface
{
    /**
     * @param object|Envelope $message The message, or a message pre-wrapped in an envelope
     */
    public function __construct(
        private object $message,
    ) {
    }

    /**
     * @return object|Envelope
     */
    public function getMessage(): object
    {
        return $this->message;
    }
}
