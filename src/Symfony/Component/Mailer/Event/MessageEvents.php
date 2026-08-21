<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Event;

use Symfony\Component\Mime\RawMessage;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class MessageEvents
{
    /**
     * @var MessageEvent[]
     */
    private array $events = [];

    /**
     * @var array<string, bool>
     */
    private array $transports = [];

    public function add(MessageEvent $event): void
    {
        $this->events[] = $event;
        $this->transports[$event->getTransport()] = true;
    }

    public function getTransports(): array
    {
        return array_keys($this->transports);
    }

    /**
     * @return MessageEvent[]
     */
    public function getEvents(?string $name = null): array
    {
        if (null === $name) {
            return $this->events;
        }

        $events = [];
        foreach ($this->events as $event) {
            if ($name === $event->getTransport()) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * @return RawMessage[]
     */
    public function getMessages(?string $name = null): array
    {
        $messages = [];
        $sent = 0;

        // an email that is queued and then sent in the same process is reported by two events; keep only the sent one
        foreach (array_reverse($this->getEvents($name)) as $event) {
            if (!$event->isQueued()) {
                ++$sent;
            } elseif ($sent > 0) {
                --$sent;

                continue;
            }

            $messages[] = $event->getMessage();
        }

        return array_reverse($messages);
    }
}
