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

use Doctrine\Persistence\ConnectionRegistry;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Wraps all handlers in a single DBAL transaction.
 *
 * Unlike DoctrineTransactionMiddleware, this middleware does not flush any
 * entity manager before committing: flushing is up to the handlers.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class DoctrineDbalTransactionMiddleware extends AbstractDoctrineDbalMiddleware
{
    public function __construct(
        ConnectionRegistry $connectionRegistry,
        private readonly ?string $connectionName = null,
    ) {
        parent::__construct($connectionRegistry);
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $connection = $this->getConnection($this->connectionName);

        $connection->beginTransaction();

        $success = false;
        try {
            $envelope = $stack->next()->handle($envelope, $stack);
            $connection->commit();

            $success = true;

            return $envelope;
        } catch (\Throwable $exception) {
            if ($exception instanceof HandlerFailedException) {
                // Remove all HandledStamp from the envelope so the retry will execute all handlers again.
                // When a handler fails, the queries of allegedly successful previous handlers just got rolled back.
                throw new HandlerFailedException($exception->getEnvelope()->withoutAll(HandledStamp::class), $exception->getWrappedExceptions());
            }

            throw $exception;
        } finally {
            if (!$success && $connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }
}
