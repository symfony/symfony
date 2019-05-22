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
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Closes connection and therefore saves number of connections.
 *
 * @author Fuong <insidestyles@gmail.com>
 *
 * @experimental in 4.3
 */
class DoctrineCloseConnectionMiddleware extends AbstractDoctrineMiddleware
{
    protected function handleForManager(EntityManagerInterface $entityManager, Envelope $envelope, StackInterface $stack): Envelope
    {
        try {
            $connection = $entityManager->getConnection();

            return $stack->next()->handle($envelope, $stack);
        } finally {
            $connection->close();
        }
    }
}
