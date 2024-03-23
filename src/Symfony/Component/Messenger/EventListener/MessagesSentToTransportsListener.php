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

    public function getSentMessagesByFQCN(array $messages, ?string $className): array
    {
        return array_filter($this->sentMessages, fn ($message) => $message['message']::class === $className);
    }

    public function getSentMessages(?string $busName, ?string $messageFQCN): array
    {
        if (null === $busName && null === $messageFQCN) {
            return $this->sentMessages;
        }

        if (null === $busName) {
            return array_filter($this->sentMessages, fn ($message) => $message['message']::class === $messageFQCN);
        }

        if (null === $messageFQCN) {
            return array_filter($this->sentMessages, fn ($message) => $message['busName'] === $busName);
        }

        return array_filter(
            array_filter($this->sentMessages, fn ($message) => $message['message']::class === $messageFQCN),
            fn ($message) => $message['busName'] === $busName
        );
    }

    public function reset(): void
    {
        $this->sentMessages = [];
    }
}
