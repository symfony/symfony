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

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Search for open transactions, then log and close them.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class DoctrineTransactionWatchdogMiddleware extends AbstractDoctrineMiddleware
{
    public function __construct(ManagerRegistry $managerRegistry, LoggerInterface $logger = null, string $entityManagerName = null)
    {
        parent::__construct($managerRegistry, $entityManagerName);

        $this->logger = $logger ?? new NullLogger();
    }

    protected function handleForManager(EntityManagerInterface $entityManager, Envelope $envelope, StackInterface $stack): Envelope
    {
        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            if ($entityManager->getConnection()->isTransactionActive()) {
                $this->logger->error('An handler open a transaction but did not close it.', [
                    'message' => $envelope->getMessage(),
                ]);

                $connection = $entityManager->getConnection();
                while ($connection->isTransactionActive()) {
                    $connection->rollBack();
                }
            }
        }
    }
}
