<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\WorkerWarmer;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

/**
 * Opens DBAL connections before a worker handles its first request.
 *
 * Failures are logged instead of thrown: connections still open lazily on first use.
 *
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
final class ConnectionWarmer
{
    /**
     * @param iterable<string, Connection> $connections Connections indexed by name
     */
    public function __construct(
        private iterable $connections,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(): void
    {
        foreach ($this->connections as $name => $connection) {
            try {
                $connection->getNativeConnection();
            } catch (\Exception $e) {
                $this->logger?->warning('Failed to preconnect the "{name}" DBAL connection: {message}', ['name' => $name, 'message' => $e->getMessage(), 'exception' => $e]);
            }
        }
    }
}
