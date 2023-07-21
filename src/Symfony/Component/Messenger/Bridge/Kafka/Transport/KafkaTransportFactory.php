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
use Symfony\Component\Messenger\Bridge\Kafka\Callback\LoggingErrorCallback;
use Symfony\Component\Messenger\Bridge\Kafka\Callback\LoggingLogCallback;
use Symfony\Component\Messenger\Bridge\Kafka\Callback\LoggingRebalanceCallback;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class KafkaTransportFactory implements TransportFactoryInterface
{
    private KafkaFactory $kafkaFactory;

    public function __construct(
        private LoggerInterface $logger = new NullLogger(),
        KafkaFactory $kafkaFactory = null,
    ) {
        if (!$kafkaFactory instanceof KafkaFactory) {
            $this->kafkaFactory = new KafkaFactory(
                new LoggingLogCallback($logger),
                new LoggingErrorCallback($logger),
                new LoggingRebalanceCallback($logger),
            );
        }
    }

    public function createTransport(#[\SensitiveParameter] string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        return new KafkaTransport(Connection::fromDsn($dsn, $options, $this->logger, $this->kafkaFactory), $serializer);
    }

    public function supports(#[\SensitiveParameter] string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'kafka://');
    }
}
