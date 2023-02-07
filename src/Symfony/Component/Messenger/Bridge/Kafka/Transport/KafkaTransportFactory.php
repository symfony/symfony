<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Kafka\Transport;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;

/**
 * @author Konstantin Scheumann <konstantin@konstantin.codes>
 */
class KafkaTransportFactory implements TransportFactoryInterface
{
    private LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    public function supports(string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'kafka://');
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): KafkaTransport
    {
        if (false === $parsedUrl = parse_url($dsn)) {
            throw new InvalidArgumentException(sprintf('The given Kafka DSN "%s" is invalid.', $dsn));
        }

        parse_str($parsedUrl['query'] ?? '', $parsedQuery);
        $options = array_replace($parsedQuery, $options);
        $options['conf']['metadata.broker.list'] = $parsedUrl['host'];

        return new KafkaTransport($this->logger, $serializer, new RdKafkaFactory(), $options);
    }
}
