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
use Doctrine\Persistence\ConnectionRegistry;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;

/**
 * @internal
 */
abstract class AbstractDoctrineDbalMiddleware implements MiddlewareInterface
{
    public function __construct(
        protected ConnectionRegistry $connectionRegistry,
    ) {
    }

    final protected function getConnection(?string $name = null): Connection
    {
        try {
            if (!($connection = $this->connectionRegistry->getConnection($name)) instanceof Connection) {
                throw new UnrecoverableMessageHandlingException(\sprintf('Expected "%s" to be a DBAL connection, but got a "%s".', $name ?? $this->connectionRegistry->getDefaultConnectionName(), $connection::class));
            }
        } catch (\InvalidArgumentException $e) {
            throw new UnrecoverableMessageHandlingException($e->getMessage(), 0, $e);
        }

        return $connection;
    }

    /**
     * @param list<string> $names or none to get every DBAL connection
     *
     * @return array<string, Connection> indexed by their name
     */
    final protected function getConnections(array $names = []): array
    {
        $connections = [];

        try {
            foreach ($names ?: array_keys($this->connectionRegistry->getConnectionNames()) as $name) {
                if (($connection = $this->connectionRegistry->getConnection($name)) instanceof Connection) {
                    $connections[$name] = $connection;
                } elseif ($names) {
                    throw new UnrecoverableMessageHandlingException(\sprintf('Expected "%s" to be a DBAL connection, but got a "%s".', $name, $connection::class));
                }
            }
        } catch (\InvalidArgumentException $e) {
            throw new UnrecoverableMessageHandlingException($e->getMessage(), 0, $e);
        }

        return $connections;
    }
}
