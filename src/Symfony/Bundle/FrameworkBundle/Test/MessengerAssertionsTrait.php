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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\EventListener\StopWorkerOnIdleListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
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
     * Handles the messages queued on an in-memory transport with the message bus of the application.
     *
     * The worker listeners of the application run as in production: a failing message is sent for
     * retry or to the failure transport as configured. A message sent for retry is consumed again
     * without waiting for its delay, so that the retries of a test play out within the call, and
     * the failure that exhausts them is rethrown. A message the application delayed itself is left
     * in the transport until it is due.
     *
     * @param int|null $limit Stops after this number of messages, instead of draining the queue
     *
     * @return int The number of messages that were handled
     */
    public static function consumeQueuedMessages(string $transport, ?int $limit = null): int
    {
        $container = static::getContainer();
        $receiver = self::createRetryAwareReceiver(self::getMessengerTransport($transport));
        $bus = $container->get('messenger.routable_message_bus');
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $container->get('event_dispatcher');

        $handled = 0;
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
        }

        foreach ($subscribers as $subscriber) {
            $dispatcher->addSubscriber($subscriber);
        }
        $dispatcher->addListener(WorkerMessageHandledEvent::class, $countHandled);
        $dispatcher->addListener(WorkerMessageFailedEvent::class, $recordFailure);

        try {
            (new Worker([$transport => $receiver], $bus, $dispatcher))->run(['sleep' => 0]);
        } finally {
            foreach ($subscribers as $subscriber) {
                $dispatcher->removeSubscriber($subscriber);
            }
            $dispatcher->removeListener(WorkerMessageHandledEvent::class, $countHandled);
            $dispatcher->removeListener(WorkerMessageFailedEvent::class, $recordFailure);
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
     * Reads the messages sent for retry without waiting for their delay, so that a test does not have to.
     */
    private static function createRetryAwareReceiver(InMemoryTransport $transport): ReceiverInterface
    {
        return new class($transport) implements ReceiverInterface {
            public function __construct(
                private InMemoryTransport $transport,
            ) {
            }

            /**
             * @return list<Envelope>
             */
            public function get(): iterable
            {
                $fetchSize = \func_num_args() > 0 ? max(1, func_get_arg(0)) : 1;
                $envelopes = [];

                foreach ($this->transport->get($fetchSize) as $envelope) {
                    $envelopes[] = $envelope;
                }

                if ($envelopes) {
                    return $envelopes;
                }

                foreach ($this->transport->all() as $envelope) {
                    if (!$envelope->last(RedeliveryStamp::class)) {
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
