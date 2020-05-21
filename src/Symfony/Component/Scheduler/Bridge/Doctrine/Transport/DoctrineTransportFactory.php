<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Doctrine\Transport;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Persistence\ConnectionRegistry;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use Symfony\Component\Scheduler\Exception\TransportException;
use Symfony\Component\Scheduler\Task\TaskFactoryInterface;
use Symfony\Component\Scheduler\Transport\Dsn;
use Symfony\Component\Scheduler\Transport\TransportFactoryInterface;
use Symfony\Component\Scheduler\Transport\TransportInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DoctrineTransportFactory implements TransportFactoryInterface
{
    private $registry;
    private $taskFactory;

    public function __construct($registry, TaskFactoryInterface $taskFactory)
    {
        if (!$registry instanceof ManagerRegistry && !$registry instanceof ConnectionRegistry) {
            throw new \TypeError(sprintf('Expected an instance of "%s" or "%s", but got "%s".', Registry::class, ConnectionRegistry::class, get_debug_type($registry)));
        }

        $this->registry = $registry;
        $this->taskFactory = $taskFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function createTransport(Dsn $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        try {
            $driverConnection = $this->registry->getConnection($dsn->getOption('connection'));
        } catch (\InvalidArgumentException $e) {
            throw new TransportException(sprintf('Could not find Doctrine connection from Scheduler DSN "%s".', $dsn), 0, $e);
        }

        $connection = new Connection($this->taskFactory, [], $driverConnection, $serializer);

        return new DoctrineTransport($dsn, $options, $connection);
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn, array $options = []): bool
    {
        return 0 === strpos($dsn, 'doctrine://');
    }
}
