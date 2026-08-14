<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Execution;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\DelayedMessageHandlingException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\ValidationFailedException;
use Symfony\Component\Messenger\Stamp\AckStamp;
use Symfony\Component\Messenger\Stamp\NoAutoAckStamp;

/**
 * @internal
 */
final class ParallelExecutionFailureSanitizer
{
    public static function decorateFailedEnvelope(Envelope $envelope): Envelope
    {
        return $envelope
            ->withoutStampsOfType(AckStamp::class)
            ->withoutStampsOfType(NoAutoAckStamp::class);
    }

    public static function sanitizeError(\Throwable $error, Envelope $envelope): \Throwable
    {
        if ($error instanceof HandlerFailedException) {
            return new HandlerFailedException($envelope, self::sanitizeWrappedExceptions($error->getWrappedExceptions(), $envelope));
        }

        if ($error instanceof DelayedMessageHandlingException) {
            return new DelayedMessageHandlingException(self::sanitizeWrappedExceptions($error->getWrappedExceptions(), $envelope), $envelope);
        }

        if ($error instanceof ValidationFailedException) {
            return new ValidationFailedException($error->getViolatingMessage(), $error->getViolations(), $envelope);
        }

        return $error;
    }

    private static function sanitizeWrappedExceptions(array $exceptions, Envelope $envelope): array
    {
        foreach ($exceptions as $key => $exception) {
            $exceptions[$key] = self::sanitizeError($exception, $envelope);
        }

        return $exceptions;
    }
}
