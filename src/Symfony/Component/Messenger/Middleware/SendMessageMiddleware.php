<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Middleware;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Exception\NoSenderForMessageException;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 * @author Tobias Schultze <http://tobion.de>
 */
class SendMessageMiddleware implements MiddlewareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private SendersLocatorInterface $sendersLocator,
        private ?EventDispatcherInterface $eventDispatcher = null,
        private bool $allowNoSenders = true,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $context = [
            'class' => $envelope->getMessage()::class,
        ];

        $sender = null;

        if ($envelope->all(ReceivedStamp::class)) {
            // it's a received message, do not send it back
            $this->logger?->info('Received message {class}', $context);
        } else {
            $shouldDispatchEvent = true;
            $senders = $this->sendersLocator->getSenders($envelope);
            $senders = \is_array($senders) ? $senders : iterator_to_array($senders);
            foreach ($senders as $alias => $sender) {
                if (null !== $this->eventDispatcher && $shouldDispatchEvent) {
                    $event = new SendMessageToTransportsEvent($envelope, $senders);
                    $this->eventDispatcher->dispatch($event);
                    $envelope = $event->getEnvelope();
                    $shouldDispatchEvent = false;
                }

                $this->logger?->info('Sending message {class} with {alias} sender using {sender}', $context + ['alias' => $alias, 'sender' => $sender::class]);
                $envelope = $sender->send($envelope->with(new SentStamp($sender::class, \is_string($alias) ? $alias : null)));
            }

            if (!$this->allowNoSenders && !$sender) {
                throw new NoSenderForMessageException(sprintf('No sender for message "%s".', $context['class']));
            }
        }

        if (null === $sender) {
            return $stack->next()->handle($envelope, $stack);
        }

        // message should only be sent and not be handled by the next middleware
        return $envelope;
    }
}
