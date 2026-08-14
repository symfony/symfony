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
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Middleware to log when transaction has been left open.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class DoctrineDbalOpenTransactionLoggerMiddleware extends AbstractDoctrineDbalMiddleware
{
    /** @var list<string> */
    private array $connectionNames;

    private bool $isHandling = false;

    /**
     * @param list<string>|string $connectionNames or none to check them all
     */
    public function __construct(
        ConnectionRegistry $connectionRegistry,
        private readonly ?LoggerInterface $logger = null,
        array|string $connectionNames = [],
    ) {
        parent::__construct($connectionRegistry);

        $this->connectionNames = (array) $connectionNames;
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if ($this->isHandling) {
            return $stack->next()->handle($envelope, $stack);
        }

        $connections = $this->getConnections($this->connectionNames);
        $initialTransactionLevels = array_map(
            static fn (Connection $connection) => $connection->getTransactionNestingLevel(),
            $connections,
        );

        $this->isHandling = true;

        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            $connectionsWithUnclosedTransactions = array_filter(
                $connections,
                static fn (Connection $connection, string $name) => $connection->getTransactionNestingLevel() > $initialTransactionLevels[$name],
                \ARRAY_FILTER_USE_BOTH,
            );
            if ($connectionsWithUnclosedTransactions) {
                $this->logger?->error('A handler opened a transaction but did not close it.', [
                    'connections' => array_keys($connectionsWithUnclosedTransactions),
                    'message' => $envelope->getMessage(),
                ]);
            }
            $this->isHandling = false;
        }
    }
}
