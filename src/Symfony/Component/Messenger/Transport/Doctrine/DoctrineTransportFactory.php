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

use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @author Vincent Touzet <vincent.touzet@gmail.com>
 *
 * @experimental in 4.3
 */
class DoctrineTransportFactory implements TransportFactoryInterface
{
    private $registry;

    public function __construct(RegistryInterface $registry)
    {
        $this->registry = $registry;
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        $configuration = Connection::buildConfiguration($dsn, $options);

        try {
            $driverConnection = $this->registry->getConnection($configuration['connection']);
        } catch (\InvalidArgumentException $e) {
            throw new TransportException(sprintf('Could not find Doctrine connection from DSN "%s".', $dsn), 0, $e);
        }

        $connection = new Connection($configuration, $driverConnection);

        return new DoctrineTransport($connection, $serializer);
    }

    public function supports(string $dsn, array $options): bool
    {
        return 0 === strpos($dsn, 'doctrine://');
    }
}
