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
use Symfony\Component\Messenger\Stamp\BusNameStamp;

/**
 * Bus of buses that is routable using a BusNameStamp.
 *
 * This is useful when passed to Worker: messages received
 * from the transport can be sent to the correct bus.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class RoutableMessageBus implements MessageBusInterface
{
    private $busLocator;
    private $fallbackBus;

    public function __construct(ContainerInterface $busLocator, ?MessageBusInterface $fallbackBus = null)
    {
        $this->busLocator = $busLocator;
        $this->fallbackBus = $fallbackBus;
    }

    public function dispatch(object $envelope, array $stamps = []): Envelope
    {
        if (!$envelope instanceof Envelope) {
            throw new InvalidArgumentException('Messages passed to RoutableMessageBus::dispatch() must be inside an Envelope.');
        }

        /** @var BusNameStamp|null $busNameStamp */
        $busNameStamp = $envelope->last(BusNameStamp::class);

        if (null === $busNameStamp) {
            if (null === $this->fallbackBus) {
                throw new InvalidArgumentException('Envelope is missing a BusNameStamp and no fallback message bus is configured on RoutableMessageBus.');
            }

            return $this->fallbackBus->dispatch($envelope, $stamps);
        }

        return $this->getMessageBus($busNameStamp->getBusName())->dispatch($envelope, $stamps);
    }

    /**
     * @internal
     */
    public function getMessageBus(string $busName): MessageBusInterface
    {
        if (!$this->busLocator->has($busName)) {
            throw new InvalidArgumentException(sprintf('Bus named "%s" does not exist.', $busName));
        }

        return $this->busLocator->get($busName);
    }
}
