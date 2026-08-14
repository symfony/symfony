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
use Doctrine\Persistence\ConnectionRegistry;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;

/**
 * Checks whether connections are still open and reconnects them otherwise.
 *
 * @author Fuong <insidestyles@gmail.com>
 */
class DoctrineDbalPingConnectionMiddleware extends AbstractDoctrineDbalMiddleware
{
    /** @var list<string> */
    private array $connectionNames;

    /**
     * @param list<string>|string $connectionNames or none to ping them all
     */
    public function __construct(ConnectionRegistry $connectionRegistry, array|string $connectionNames = [])
    {
        parent::__construct($connectionRegistry);

        $this->connectionNames = (array) $connectionNames;
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (null !== $envelope->last(ConsumedByWorkerStamp::class)) {
            // Ping every connection and let healthy ones proceed even if another one failed.
            // The first failure is rethrown at the end.
            $firstFailure = null;
            foreach ($this->getConnections($this->connectionNames) as $connection) {
                try {
                    $this->pingConnection($connection);
                } catch (DBALException $e) {
                    $firstFailure ??= $e;
                }
            }

            if (null !== $firstFailure) {
                throw $firstFailure;
            }
        }

        return $stack->next()->handle($envelope, $stack);
    }

    private function pingConnection(Connection $connection): void
    {
        if (!$connection->isConnected()) {
            return;
        }

        try {
            $this->executeDummySql($connection);
        } catch (DBALException) {
            $connection->close();
            // Attempt to reestablish the lazy connection by sending another query.
            $this->executeDummySql($connection);
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
