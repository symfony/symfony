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

/**
 * Holds the message whose failure caused the current message to be dispatched.
 *
 * @see DispatchOnFailureStamp
 */
final class FailedMessageStamp implements StampInterface
{
    public function __construct(
        private object $message,
    ) {
    }

    public function getMessage(): object
    {
        return $this->message;
    }
}
