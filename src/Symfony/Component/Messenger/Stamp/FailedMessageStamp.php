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
 * Holds the message whose failure caused the current message to be dispatched.
 *
 * @see DispatchOnFailureStamp
 */
final class FailedMessageStamp implements StampInterface
{
    private object $message;

    /**
     * @param object|Envelope $message The message that failed, or its envelope, of which only the message is kept
     */
    public function __construct(object $message)
    {
        $this->message = $message instanceof Envelope ? $message->getMessage() : $message;
    }

    public function getMessage(): object
    {
        return $this->message;
    }
}
