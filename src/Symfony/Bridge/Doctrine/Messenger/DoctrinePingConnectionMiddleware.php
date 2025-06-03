<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Messenger;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;

/**
 * Checks whether the connection is still open or reconnects otherwise.
 *
 * @author Fuong <insidestyles@gmail.com>
 */
class DoctrinePingConnectionMiddleware extends AbstractDoctrineMiddleware
{
    protected function handleForManager(EntityManagerInterface $entityManager, Envelope $envelope, StackInterface $stack): Envelope
    {
        if (null !== $envelope->last(ConsumedByWorkerStamp::class)) {
            $this->pingConnection($entityManager);
        }

        return $stack->next()->handle($envelope, $stack);
    }

    private function pingConnection(EntityManagerInterface $entityManager): void
    {
        $connection = $entityManager->getConnection();

        try {
            $this->executeDummySql($connection);
        } catch (DBALException) {
            $connection->close();
            // Attempt to reestablish the lazy connection by sending another query.
            $this->executeDummySql($connection);
        }

        if (!$entityManager->isOpen()) {
            $this->managerRegistry->resetManager($this->entityManagerName);
        }
    }

    /**
     * @throws DBALException
     */
    private function executeDummySql(Connection $connection): void
    {
        $connection->executeQuery($connection->getDatabasePlatform()->getDummySelectSQL());
    }
}
