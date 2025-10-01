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

use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Smaïne Milianni <smaine.milianni@gmail.com>
 */
final class SentMessageEvent extends Event
{
    public function __construct(
        private SentMessage $message,
    ) {
    }

    public function getMessage(): SentMessage
    {
        return $this->message;
    }
}
