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

use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;

/**
 * Bus of buses that is routable by reading the ReceivedStamp and locating the bus for the given transport name.
 *
 * This is useful when passed to Worker: messages received
 * from the transport can be sent to the correct bus.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 * @author Tobias Schultze <http://tobion.de>
 */
class RoutableMessageBus implements MessageBusInterface
{
    private $busLocator;

    public function __construct(ContainerInterface $busLocator)
    {
        $this->busLocator = $busLocator;
    }

    public function dispatch($envelope, array $stamps = []): Envelope
    {
        if (!$envelope instanceof Envelope) {
            throw new InvalidArgumentException('Messages passed to RoutableMessageBus::dispatch() must be inside an Envelope');
        }

        /** @var ReceivedStamp|null $receivedStamp */
        $receivedStamp = $envelope->last(ReceivedStamp::class);

        if (null === $receivedStamp) {
            // The RoutableMessageBus is only used in Worker context where the ReceivedStamp is added. So this should not happen.
            throw new InvalidArgumentException('Envelope is missing a ReceivedStamp.');
        }

        $transportName = $receivedStamp->getTransportName();

        /** @var SentToFailureTransportStamp|null $sentToFailureStamp */
        $sentToFailureStamp = $envelope->last(SentToFailureTransportStamp::class);
        if (null !== $sentToFailureStamp) {
            // if the message was received from the failure transport, mark the message as received from the original transport and use the bus based on it
            // this guarantees the same behavior when consuming from the failure transport (directly or via messenger:failed:retry) as when originally received
            $originalReceiver = $sentToFailureStamp->getOriginalReceiverName();
            // in case the original receiver does not exist anymore, use the bus configured for failure transport
            if ($this->busLocator->has($originalReceiver)) {
                $transportName = $originalReceiver;
            }

            $envelope = $envelope->with(new ReceivedStamp($originalReceiver));
        }

        return $this->getMessageBusForTransport($transportName)->dispatch($envelope, $stamps);
    }

    private function getMessageBusForTransport(string $transportName): MessageBusInterface
    {
        if (!$this->busLocator->has($transportName)) {
            throw new InvalidArgumentException(sprintf('Could not find a bus for transport "%s".', $transportName));
        }

        return $this->busLocator->get($transportName);
    }
}
