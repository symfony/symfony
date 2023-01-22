<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\AmazonSqs\Transport;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class AmazonSqsTransportFactory implements TransportFactoryInterface
{
    private ?LoggerInterface $logger;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    public function createTransport(#[\SensitiveParameter] string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        unset($options['transport_name']);

        return new AmazonSqsTransport(Connection::fromDsn($dsn, $options, null, $this->logger), $serializer);
    }

    public function supports(#[\SensitiveParameter] string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'sqs://') || preg_match('#^https://sqs\.[\w\-]+\.amazonaws\.com/.+#', $dsn);
    }
}
