<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Sync;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Creates sync transports.
 *
 * The "retry" and "failure_transport" boolean options, given in the DSN query or in
 * the transport options, enable the retry strategy and the failure transport that are
 * configured for the transport. Both locators are keyed by transport name.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 *
 * @implements TransportFactoryInterface<SyncTransport>
 */
class SyncTransportFactory implements TransportFactoryInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private ?ContainerInterface $retryStrategyLocator = null,
        private ?ContainerInterface $failureSenderLocator = null,
        private ?EventDispatcherInterface $eventDispatcher = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function createTransport(#[\SensitiveParameter] string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        $query = [];
        if ($queryAsString = strstr($dsn, '?')) {
            parse_str(ltrim($queryAsString, '?'), $query);
        }
        $options += $query;
        $name = $options['transport_name'] ?? 'sync';

        $retryStrategy = null;
        if (self::parseBoolean($options['retry'] ?? false, 'retry', $name)) {
            if (!$this->retryStrategyLocator?->has($name)) {
                throw new InvalidArgumentException(\sprintf('The "retry" option is enabled on the "%s" transport but no retry strategy is configured for it.', $name));
            }
            $retryStrategy = $this->retryStrategyLocator->get($name);
        }

        $failureSender = null;
        if (self::parseBoolean($options['failure_transport'] ?? false, 'failure_transport', $name)) {
            if (!$this->failureSenderLocator?->has($name)) {
                throw new InvalidArgumentException(\sprintf('The "failure_transport" option is enabled on the "%s" transport but no failure transport is configured for it.', $name));
            }
            $failureSender = $this->failureSenderLocator->get($name);
        }

        return new SyncTransport($this->messageBus, $retryStrategy, $failureSender, $this->eventDispatcher, $this->logger);
    }

    public function supports(#[\SensitiveParameter] string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'sync://');
    }

    private static function parseBoolean(mixed $value, string $option, string $name): bool
    {
        return filter_var($value, \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE) ?? throw new InvalidArgumentException(\sprintf('The "%s" option of the "%s" transport must be a boolean.', $option, $name));
    }
}
