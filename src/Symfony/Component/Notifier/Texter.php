<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier;

use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Notifier\Event\MessageEvent;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Texter implements TexterInterface
{
    public function __construct(
        private TransportInterface $transport,
        private ?MessageBusInterface $bus = null,
        private ?EventDispatcherInterface $dispatcher = null,
    ) {
    }

    public function __toString(): string
    {
        return 'texter';
    }

    public function supports(MessageInterface $message): bool
    {
        return $this->transport->supports($message);
    }

    public function send(MessageInterface $message): ?SentMessage
    {
        if (null === $this->bus) {
            return $this->transport->send($message);
        }

        $this->dispatcher?->dispatch(new MessageEvent($message, true));

        $this->bus->dispatch($message);

        return null;
    }
}
