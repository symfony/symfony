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
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;

trigger_deprecation('symfony/doctrine-bridge', '8.2', 'The "%s" class is deprecated, use "%s" instead.', DoctrineCloseConnectionMiddleware::class, DoctrineDbalCloseConnectionMiddleware::class);

/**
 * Closes connection and therefore saves number of connections.
 *
 * @author Fuong <insidestyles@gmail.com>
 *
 * @deprecated since Symfony 8.2, use DoctrineDbalCloseConnectionMiddleware instead
 */
class DoctrineCloseConnectionMiddleware extends AbstractDoctrineMiddleware
{
    protected function handleForManager(EntityManagerInterface $entityManager, Envelope $envelope, StackInterface $stack): Envelope
    {
        try {
            $connection = $entityManager->getConnection();

            return $stack->next()->handle($envelope, $stack);
        } finally {
            if (null !== $envelope->last(ConsumedByWorkerStamp::class)) {
                $connection->close();
            }
        }
    }
}
