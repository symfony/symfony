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

use Psr\Log\LogLevel;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\RecoverableExceptionInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

/**
 * Trait for retry strategies containing default log severity rules.
 */
trait RetryRecoveryBehaviorTrait
{
    /**
     * @param \Throwable|null $throwable The cause of the failed handling
     */
    public function isRetryable(Envelope $message, \Throwable $throwable = null): bool
    {
        if (null !== $throwable) {
            // A failure is either unrecoverable, recoverable or neutral
            if ($this->isUnrecoverable($throwable)) {
                return false;
            }

            if ($this->isRecoverable($throwable)) {
                return true;
            }
        }

        $retries = RedeliveryStamp::getRetryCountFromEnvelope($message);

        return $retries < $this->maxRetries;
    }

    /**
     * @return string The \Psr\Log\LogLevel log severity
     */
    public function getLogSeverity(Envelope $message, \Throwable $throwable = null): string
    {
        return $this->isRetryable($message, $throwable)
            ? LogLevel::WARNING
            : LogLevel::CRITICAL;
    }

    /**
     * Determine if exception was unrecoverable.
     *
     * Unrecoverable exceptions should never be retried
     */
    private function isUnrecoverable(\Throwable $throwable): bool
    {
        return ($throwable instanceof HandlerFailedException)
            ? $throwable->isUnrecoverable()
            : $throwable instanceof UnrecoverableExceptionInterface;
    }

    /**
     * Determine if exception was recoverable.
     *
     * Recoverable exceptions should always be retried
     */
    private function isRecoverable(\Throwable $throwable): bool
    {
        return ($throwable instanceof HandlerFailedException)
            ? $throwable->isRecoverable()
            : $throwable instanceof RecoverableExceptionInterface;
    }
}
