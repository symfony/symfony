<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Doctrine\Transport;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\Persistence\ConnectionRegistry;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Serialization\IgbinarySerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @author Vincent Touzet <vincent.touzet@gmail.com>
 *
 * @implements TransportFactoryInterface<DoctrineTransport>
 */
class DoctrineTransportFactory implements TransportFactoryInterface
{
    private ConnectionRegistry $registry;

    public function __construct(ConnectionRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function createTransport(#[\SensitiveParameter] string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        $useNotify = ($options['use_notify'] ?? true);
        unset($options['transport_name'], $options['use_notify']);
        // Always allow PostgreSQL-specific keys, to be able to transparently fallback to the native driver when LISTEN/NOTIFY isn't available
        $configuration = PostgreSqlConnection::buildConfiguration($dsn, $options);

        try {
            /** @var \Doctrine\DBAL\Connection */
            $driverConnection = $this->registry->getConnection($configuration['connection']);
        } catch (\InvalidArgumentException $e) {
            throw new TransportException('Could not find Doctrine connection from Messenger DSN.', 0, $e);
        }

        $platform = $driverConnection->getDatabasePlatform();
        if ($platform instanceof SqlitePlatform) {
            throw new TransportException(sprintf('Sqlite does not support storing binary content. Do not use %s as default Messenger serializer.', IgbinarySerializer::class), 0);
        }

        $configuration['binary_body'] = $serializer instanceof IgbinarySerializer;

        if ($useNotify && $driverConnection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $connection = new PostgreSqlConnection($configuration, $driverConnection);
        } else {
            $connection = new Connection($configuration, $driverConnection);
        }

        return new DoctrineTransport($connection, $serializer);
    }

    public function supports(#[\SensitiveParameter] string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'doctrine://');
    }
}
