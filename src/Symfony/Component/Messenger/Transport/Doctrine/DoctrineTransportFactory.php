<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Doctrine;

use Doctrine\Common\Persistence\ConnectionRegistry;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Dsn;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @author Vincent Touzet <vincent.touzet@gmail.com>
 */
class DoctrineTransportFactory implements TransportFactoryInterface
{
    private $registry;

    public function __construct(ConnectionRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function createTransport(Dsn $dsn, SerializerInterface $serializer, string $name): TransportInterface
    {
        $configuration = Connection::buildConfigurationFromDsnObject($dsn);

        try {
            $driverConnection = $this->registry->getConnection($configuration['connection']);
        } catch (\InvalidArgumentException $e) {
            throw new TransportException(sprintf('Could not find Doctrine connection from Messenger DSN "%s".', $dsn), 0, $e);
        }

        $connection = new Connection($configuration, $driverConnection);

        return new DoctrineTransport($connection, $serializer);
    }

    public function supports(Dsn $dsn): bool
    {
        return 'doctrine' === $dsn->getScheme();
    }
}
