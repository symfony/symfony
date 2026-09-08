<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Retry;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\RecoverableExceptionInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface;

/**
 * Decides whether a failed message should be retried, from the exception and the retry strategy.
 *
 * @internal
 */
final class RetryDecider
{
    public static function shouldRetry(\Throwable $e, Envelope $envelope, RetryStrategyInterface $retryStrategy): bool
    {
        return self::decideFromException($e) ?? $retryStrategy->isRetryable($envelope, $e);
    }

    /**
     * Returns true when the exception forces a retry, false when it forbids one,
     * and null when the retry strategy decides.
     */
    public static function decideFromException(\Throwable $e): ?bool
    {
        if ($e instanceof RecoverableExceptionInterface && (!method_exists($e, 'forceRetry') || $e->forceRetry())) {
            return true;
        }

        // if one or more nested Exceptions is an instance of RecoverableExceptionInterface we should retry
        // if ALL nested Exceptions are an instance of UnrecoverableExceptionInterface we should not retry
        if ($e instanceof HandlerFailedException) {
            $shouldNotRetry = true;
            foreach ($e->getWrappedExceptions() as $nestedException) {
                if ($nestedException instanceof RecoverableExceptionInterface && (!method_exists($nestedException, 'forceRetry') || $nestedException->forceRetry())) {
                    return true;
                }

                if (!$nestedException instanceof UnrecoverableExceptionInterface) {
                    $shouldNotRetry = false;
                    break;
                }
            }
            if ($shouldNotRetry) {
                return false;
            }
        }

        if ($e instanceof UnrecoverableExceptionInterface) {
            return false;
        }

        return null;
    }
}
