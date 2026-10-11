<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Test;

use Symfony\Component\EventDispatcher\ScopedEventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\EventListener\StopWorkerOnIdleListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Worker;

/**
 * Assertions and helpers for the messages queued on in-memory Messenger transports.
 */
trait MessengerAssertionsTrait
{
    /**
     * Asserts the number of messages queued on an in-memory transport.
     *
     * @param string|null $messageClass Counts only the messages that are instances of this class
     */
    public static function assertQueuedMessageCount(int $count, string $transport, ?string $messageClass = null, string $message = ''): void
    {
        $envelopes = self::getQueuedMessages($transport);

        if (null !== $messageClass) {
            if (!class_exists($messageClass) && !interface_exists($messageClass)) {
                throw new \InvalidArgumentException(\sprintf('The message class "%s" given to assertQueuedMessageCount() does not exist.', $messageClass));
            }

            $envelopes = array_filter($envelopes, static fn (Envelope $envelope) => $envelope->getMessage() instanceof $messageClass);
        }

        self::assertCount($count, $envelopes, $message ?: \sprintf('Failed asserting that the "%s" transport has %d queued message(s)%s.', $transport, $count, null === $messageClass ? '' : ' of class "'.$messageClass.'"'));
    }

    /**
     * Returns the envelopes queued on an in-memory transport, delayed ones included.
     *
     * @return Envelope[]
     */
    public static function getQueuedMessages(string $transport): array
    {
        return self::getMessengerTransport($transport)->all();
    }

    /**
     * Handles the messages queued on in-memory transports with the message bus of the application.
     *
     * Without a transport name, all the in-memory transports are consumed together, by order of priority, until none of them has a message left.
     * The messages that handlers dispatch along the way are handled too.
     * The messages sent to a failure transport are left there.
     * Without a limit, the call fails when the transports are not empty after 1000 messages, as a handler might be dispatching messages endlessly.
     *
     * The worker listeners of the application run as in production.
     * A failing message is sent for retry or to the failure transport, as configured.
     * A message sent for retry is consumed again without waiting for its delay, so that the retries of a test play out within the call.
     * The failure that exhausts the retries is rethrown.
     * A message the application delayed itself is left in the transport until it is due.
     *
     * @param string|null $transport The name of the in-memory transport to consume, instead of all of them
     * @param int|null    $limit     Stops after this number of messages, instead of draining the queues
     *
     * @return int The number of messages that were handled
     */
    public static function consumeQueuedMessages(?string $transport = null, ?int $limit = null): int
    {
        $container = static::getContainer();
        $receivers = [];

        if (null !== $transport) {
            $receivers[$transport] = self::createRetryAwareReceiver(self::getMessengerTransport($transport));
        } else {
            foreach (self::getInMemoryMessengerTransports() as $name => $inMemoryTransport) {
                $receivers[$name] = self::createRetryAwareReceiver($inMemoryTransport, true);
            }
        }

        $bus = $container->get('messenger.routable_message_bus');
        $dispatcher = new ScopedEventDispatcher($container->get('event_dispatcher'));

        $handled = 0;
        $processed = 0;
        $failures = [];
        $countHandled = static function () use (&$handled): void {
            ++$handled;
        };
        $recordFailure = static function (WorkerMessageFailedEvent $event) use (&$failures): void {
            // a failure the retry strategy replays is not the outcome of this run
            if (!$event->willRetry()) {
                $failures[] = $event->getThrowable();
            }
        };
        $subscribers = [new StopWorkerOnIdleListener()];

        if (null !== $limit) {
            $subscribers[] = new StopWorkerOnMessageLimitListener($limit);
        } else {
            $dispatcher->addListener(WorkerRunningEvent::class, static function (WorkerRunningEvent $event) use (&$processed): void {
                if (!$event->isWorkerIdle() && 1000 < ++$processed) {
                    $event->getWorker()->stop();
                }
            });
        }

        foreach ($subscribers as $subscriber) {
            $dispatcher->addSubscriber($subscriber);
        }
        $dispatcher->addListener(WorkerMessageHandledEvent::class, $countHandled);
        $dispatcher->addListener(WorkerMessageFailedEvent::class, $recordFailure);

        (new Worker($receivers, $bus, $dispatcher))->run(['sleep' => 0]);

        if (1000 < $processed) {
            static::fail(\sprintf('%s not empty after 1000 messages. A handler might be dispatching messages endlessly. Pass a limit to consumeQueuedMessages() to consume more messages.', null === $transport ? 'The in-memory Messenger transports are' : \sprintf('The "%s" Messenger transport is', $transport)));
        }

        if ($failures) {
            throw $failures[0];
        }

        return $handled;
    }

    /**
     * Returns the in-memory transport registered under the given name.
     */
    public static function getMessengerTransport(string $transport): InMemoryTransport
    {
        $container = static::getContainer();

        if (!$container->has($id = 'messenger.transport.'.$transport)) {
            static::fail(\sprintf('The "%s" Messenger transport is not registered. Did you forget to configure it under "framework.messenger.transports"?', $transport));
        }

        if (!($service = $container->get($id)) instanceof InMemoryTransport) {
            static::fail(\sprintf('The "%s" Messenger transport is not an in-memory transport. Configure "in-memory://" as its DSN in the test environment to make queued message assertions.', $transport));
        }

        return $service;
    }

    /**
     * Returns the in-memory transports of the application by name, in the order of their priority.
     *
     * @return array<string, InMemoryTransport>
     */
    private static function getInMemoryMessengerTransports(): array
    {
        $container = static::getContainer();

        if (!$container->has('messenger.receiver_locator')) {
            static::fail('Cannot list the Messenger transports. Is Messenger enabled, and is the "symfony/console" package installed?');
        }

        $receivers = $container->get('messenger.receiver_locator');
        $transports = [];

        // the locator also lists each receiver under its service id, while getMessengerTransport() accepts only transport names
        foreach (array_keys($receivers->getProvidedServices()) as $name) {
            if ($container->has('messenger.transport.'.$name) && ($receiver = $receivers->get($name)) instanceof InMemoryTransport) {
                $transports[$name] = $receiver;
            }
        }

        return $transports;
    }

    /**
     * Reads the messages sent for retry without waiting for their delay, so that a test does not have to.
     *
     * @param bool $skipFailedMessages Leaves the messages sent to a failure transport where they are
     */
    private static function createRetryAwareReceiver(InMemoryTransport $transport, bool $skipFailedMessages = false): ReceiverInterface
    {
        return new class($transport, $skipFailedMessages) implements ReceiverInterface {
            public function __construct(
                private InMemoryTransport $transport,
                private bool $skipFailedMessages,
            ) {
            }

            /**
             * @return list<Envelope>
             */
            public function get(): iterable
            {
                $fetchSize = \func_num_args() > 0 ? max(1, func_get_arg(0)) : 1;
                $envelopes = [];

                foreach ($this->transport->get(\PHP_INT_MAX) as $envelope) {
                    if ($this->skipFailedMessages && $envelope->last(SentToFailureTransportStamp::class)) {
                        continue;
                    }

                    $envelopes[] = $envelope;

                    if (\count($envelopes) >= $fetchSize) {
                        break;
                    }
                }

                if ($envelopes) {
                    return $envelopes;
                }

                foreach ($this->transport->all() as $envelope) {
                    if (!$envelope->last(RedeliveryStamp::class) || $this->skipFailedMessages && $envelope->last(SentToFailureTransportStamp::class)) {
                        continue;
                    }

                    $envelopes[] = $envelope;

                    if (\count($envelopes) >= $fetchSize) {
                        break;
                    }
                }

                return $envelopes;
            }

            public function ack(Envelope $envelope): void
            {
                $this->transport->ack($envelope);
            }

            public function reject(Envelope $envelope): void
            {
                $this->transport->reject($envelope);
            }
        };
    }
}
