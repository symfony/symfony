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

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 5.2
 */
class NotificationEvents
{
    private $events = [];
    private $transports = [];

    public function add(MessageEvent $event): void
    {
        $this->events[] = $event;
        $this->transports[$event->getMessage()->getTransport()] = true;
    }

    public function getTransports(): array
    {
        return array_keys($this->transports);
    }

    /**
     * @return MessageEvent[]
     */
    public function getEvents(string $name = null): array
    {
        if (null === $name) {
            return $this->events;
        }

        $events = [];
        foreach ($this->events as $event) {
            if ($name === $event->getMessage()->getTransport()) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * @return MessageInterface[]
     */
    public function getMessages(string $name = null): array
    {
        $events = $this->getEvents($name);
        $messages = [];
        foreach ($events as $event) {
            $messages[] = $event->getMessage();
        }

        return $messages;
    }
}
