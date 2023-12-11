<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Middleware;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\LogicException;

class UnwrapHandlerExceptionMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        try {
            return $stack->next()->handle($envelope, $stack);
        } catch (HandlerFailedException $exception) {
            $wrappedExceptions = $exception->getWrappedExceptions();

            if (1 !== \count($wrappedExceptions)) {
                throw new LogicException(sprintf('%s can only unwrap a single exception, but got %d.', __CLASS__, \count($wrappedExceptions)));
            }

            throw reset($wrappedExceptions);
        }
    }
}
