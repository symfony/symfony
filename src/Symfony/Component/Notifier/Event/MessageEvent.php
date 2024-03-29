<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Event;

use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class MessageEvent extends Event
{
    public function __construct(
        private MessageInterface $message,
        private bool $queued = false,
    ) {
    }

    public function getMessage(): MessageInterface
    {
        return $this->message;
    }

    public function isQueued(): bool
    {
        return $this->queued;
    }
}
