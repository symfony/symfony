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
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Middleware to log when transaction has been left open.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class DoctrineOpenTransactionLoggerMiddleware extends AbstractDoctrineMiddleware
{
    private bool $isHandling = false;

    public function __construct(
        ManagerRegistry $managerRegistry,
        string $entityManagerName = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
        parent::__construct($managerRegistry, $entityManagerName);
    }

    protected function handleForManager(EntityManagerInterface $entityManager, Envelope $envelope, StackInterface $stack): Envelope
    {
        if ($this->isHandling) {
            return $stack->next()->handle($envelope, $stack);
        }

        $this->isHandling = true;

        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            if ($entityManager->getConnection()->isTransactionActive()) {
                $this->logger?->error('A handler opened a transaction but did not close it.', [
                    'message' => $envelope->getMessage(),
                ]);
            }
            $this->isHandling = false;
        }
    }
}
