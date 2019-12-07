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

use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\LegacyEventDispatcherProxy;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 * @author Tobias Schultze <http://tobion.de>
 */
class SendMessageMiddleware implements MiddlewareInterface
{
    use LoggerAwareTrait;

    private $sendersLocator;
    private $eventDispatcher;

    public function __construct(SendersLocatorInterface $sendersLocator, EventDispatcherInterface $eventDispatcher = null)
    {
        $this->sendersLocator = $sendersLocator;

        if (null !== $eventDispatcher && class_exists(LegacyEventDispatcherProxy::class)) {
            $this->eventDispatcher = LegacyEventDispatcherProxy::decorate($eventDispatcher);
        } else {
            $this->eventDispatcher = $eventDispatcher;
        }

        $this->logger = new NullLogger();
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $context = [
            'message' => $envelope->getMessage(),
            'class' => \get_class($envelope->getMessage()),
        ];

        $sender = null;

        if ($envelope->all(ReceivedStamp::class)) {
            // it's a received message, do not send it back
            $this->logger->info('Received message {class}', $context);
        } else {
            $shouldDispatchEvent = true;
            foreach ($this->sendersLocator->getSenders($envelope) as $alias => $sender) {
                if (null !== $this->eventDispatcher && $shouldDispatchEvent) {
                    $event = new SendMessageToTransportsEvent($envelope);
                    $this->eventDispatcher->dispatch($event);
                    $envelope = $event->getEnvelope();
                    $shouldDispatchEvent = false;
                }

                $this->logger->info('Sending message {class} with {sender}', $context + ['sender' => \get_class($sender)]);
                $envelope = $sender->send($envelope->with(new SentStamp(\get_class($sender), \is_string($alias) ? $alias : null)));
            }
        }

        if (null === $sender) {
            return $stack->next()->handle($envelope, $stack);
        }

        // message should only be sent and not be handled by the next middleware
        return $envelope;
    }
}
