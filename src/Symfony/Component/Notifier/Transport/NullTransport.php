<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Transport;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\LegacyEventDispatcherProxy;
use Symfony\Component\Notifier\Event\MessageEvent;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\NullMessage;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 5.2
 */
class NullTransport implements TransportInterface
{
    private $dispatcher;

    public function __construct(EventDispatcherInterface $dispatcher = null)
    {
        $this->dispatcher = class_exists(Event::class) ? LegacyEventDispatcherProxy::decorate($dispatcher) : $dispatcher;
    }

    public function send(MessageInterface $message): SentMessage
    {
        $message = new NullMessage($message);

        if (null !== $this->dispatcher) {
            $this->dispatcher->dispatch(new MessageEvent($message));
        }

        return new SentMessage($message, (string) $this);
    }

    public function __toString(): string
    {
        return 'null';
    }

    public function supports(MessageInterface $message): bool
    {
        return true;
    }
}
