<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;

class KernelTerminateTransportFactory implements TransportFactoryInterface
{
    private $busLocator;
    private $eventDispatcher;

    public function __construct(ContainerInterface $busLocator, EventDispatcherInterface $eventDispatcher)
    {
        $this->busLocator = $busLocator;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function createTransport(string $dsn, array $options): TransportInterface
    {
        if (false === $parsedUrl = parse_url($dsn)) {
            throw new InvalidArgumentException(sprintf('The given kernel.terminate DSN "%s" is invalid.', $dsn));
        }

        $kernelTerminateBusId = $transport['options']['bus'] ?? null;
        if ($kernelTerminateBusId && !$this->busLocator->has($kernelTerminateBusId)) {
            throw new InvalidArgumentException(sprintf('No bus with id "%s" was found in framework.messenger.buses config for transport "%s". Known buses are %s.', $kernelTerminateBusId, $name, json_encode(array_keys($buses))));
        }

        $parsedQuery = array();
        parse_str($parsedUrl['query'] ?? null, $parsedQuery);
        $busId = $parsedQuery['bus'] ?? $options['bus'] ?? null;

        if (null === $busId) {
            throw new InvalidArgumentException(sprintf('Missing mandatory "bus" option for kernel.terminate transport with DSN "%s"', $dsn));
        }

        if (!$this->busLocator->has($busId)) {
            throw new InvalidArgumentException(sprintf('No bus was found with id "%s" for kernel.terminate transport with DSN "%s"', $busId, $dsn));
        }

        $transport = new MemoryTransport($this->busLocator->get($busId));

        $this->eventDispatcher->addListener(KernelEvents::TERMINATE, array($transport, 'flush'));
        $this->eventDispatcher->addListener(KernelEvents::EXCEPTION, array($transport, 'stop'));

        return $transport;
    }

    public function supports(string $dsn, array $options): bool
    {
        return 0 === strpos($dsn, 'symfony://kernel.terminate');
    }
}
