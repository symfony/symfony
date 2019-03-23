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
 * @experimental in 4.3
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class RoutableMessageBus implements MessageBusInterface
{
    private $busLocator;

    /**
     * @param ContainerInterface $busLocator A locator full of MessageBusInterface objects
     */
    public function __construct(ContainerInterface $busLocator)
    {
        $this->busLocator = $busLocator;
    }

    public function dispatch($envelope): Envelope
    {
        if (!$envelope instanceof Envelope) {
            throw new InvalidArgumentException('Messages passed to RoutableMessageBus::dispatch() must be inside an Envelope');
        }

        /** @var BusNameStamp $busNameStamp */
        $busNameStamp = $envelope->last(BusNameStamp::class);
        if (null === $busNameStamp) {
            throw new InvalidArgumentException('Envelope does not contain a BusNameStamp.');
        }

        if (!$this->busLocator->has($busNameStamp->getBusName())) {
            throw new InvalidArgumentException(sprintf('Invalid bus name "%s" on BusNameStamp.', $busNameStamp->getBusName()));
        }

        return $this->busLocator->get($busNameStamp->getBusName())->dispatch($envelope);
    }
}
