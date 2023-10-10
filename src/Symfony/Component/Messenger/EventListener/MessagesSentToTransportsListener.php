<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Contracts\Service\ResetInterface;

/*
 * @author Marilena Ruffelaere <marilena.ruffelaere@gmail.com>
 */

class MessagesSentToTransportsListener implements EventSubscriberInterface, ResetInterface
{
    private array $sentMessages = [];

    public static function getSubscribedEvents(): array
    {
        return [
            SendMessageToTransportsEvent::class => ['onMessageSent', -255],
        ];
    }

    public function onMessageSent(SendMessageToTransportsEvent $event): void
    {
        $envelope = $event->getEnvelope();
        $this->sentMessages[] = [
            'busName' => $envelope->last(BusNameStamp::class)->getBusName(),
            'message' => $envelope->getMessage(),
        ];
    }

    public function getSentMessages(): array
    {
        return $this->sentMessages;
    }

    public function getSentMessagesByBus(?string $busName): array
    {
        if (null === $busName) {
            return $this->getSentMessages();
        }

        return array_filter($this->sentMessages, fn ($message) => $message['busName'] === $busName);
    }

    public function getSentMessagesByClassName(?string $className): array
    {
        if (null === $className) {
            return $this->getSentMessages();
        }

        return array_filter($this->sentMessages, fn ($message) => $message['message']::class === $className);
    }

    public function reset(): void
    {
        $this->sentMessages = [];
    }
}
