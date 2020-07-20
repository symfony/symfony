<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\LegacyEventDispatcherProxy;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\RejectRedeliveredMessageException;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 * @author Tobias Schultze <http://tobion.de>
 *
 * @final
 */
class Worker
{
    private $receivers;
    private $bus;
    private $eventDispatcher;
    private $logger;
    private $shouldStop = false;

    /**
     * @param ReceiverInterface[] $receivers Where the key is the transport name
     */
    public function __construct(array $receivers, MessageBusInterface $bus, EventDispatcherInterface $eventDispatcher = null, LoggerInterface $logger = null)
    {
        $this->receivers = $receivers;
        $this->bus = $bus;
        $this->logger = $logger;
        $this->eventDispatcher = class_exists(Event::class) ? LegacyEventDispatcherProxy::decorate($eventDispatcher) : $eventDispatcher;
    }

    /**
     * Receive the messages and dispatch them to the bus.
     *
     * Valid options are:
     *  * sleep (default: 1000000): Time in microseconds to sleep after no messages are found
     */
    public function run(array $options = []): void
    {
        $this->dispatchEvent(new WorkerStartedEvent($this));

        $options = array_merge([
            'sleep' => 1000000,
        ], $options);

        while (false === $this->shouldStop) {
            $envelopeHandled = false;
            foreach ($this->receivers as $transportName => $receiver) {
                $envelopes = $receiver->get();

                foreach ($envelopes as $envelope) {
                    $envelopeHandled = true;

                    $this->handleMessage($envelope, $receiver, $transportName);
                    $this->dispatchEvent(new WorkerRunningEvent($this, false));

                    if ($this->shouldStop) {
                        break 2;
                    }
                }

                // after handling a single receiver, quit and start the loop again
                // this should prevent multiple lower priority receivers from
                // blocking too long before the higher priority are checked
                if ($envelopeHandled) {
                    break;
                }
            }

            if (false === $envelopeHandled) {
                $this->dispatchEvent(new WorkerRunningEvent($this, true));

                usleep($options['sleep']);
            }
        }

        $this->dispatchEvent(new WorkerStoppedEvent($this));
    }

    private function handleMessage(Envelope $envelope, ReceiverInterface $receiver, string $transportName): void
    {
        $event = new WorkerMessageReceivedEvent($envelope, $transportName);
        $this->dispatchEvent($event);

        if (!$event->shouldHandle()) {
            return;
        }

        try {
            $envelope = $this->bus->dispatch($envelope->with(new ReceivedStamp($transportName), new ConsumedByWorkerStamp()));
        } catch (\Throwable $throwable) {
            $rejectFirst = $throwable instanceof RejectRedeliveredMessageException;
            if ($rejectFirst) {
                // redelivered messages are rejected first so that continuous failures in an event listener or while
                // publishing for retry does not cause infinite redelivery loops
                $receiver->reject($envelope);
            }

            if ($throwable instanceof HandlerFailedException) {
                $envelope = $throwable->getEnvelope();
            }

            $this->dispatchEvent(new WorkerMessageFailedEvent($envelope, $transportName, $throwable));

            if (!$rejectFirst) {
                $receiver->reject($envelope);
            }

            return;
        }

        $this->dispatchEvent(new WorkerMessageHandledEvent($envelope, $transportName));

        if (null !== $this->logger) {
            $message = $envelope->getMessage();
            $context = [
                'message' => $message,
                'class' => \get_class($message),
            ];
            $this->logger->info('{class} was handled successfully (acknowledging to transport).', $context);
        }

        $receiver->ack($envelope);
    }

    public function stop(): void
    {
        $this->shouldStop = true;
    }

    private function dispatchEvent(object $event): void
    {
        if (null === $this->eventDispatcher) {
            return;
        }

        $this->eventDispatcher->dispatch($event);
    }
}
