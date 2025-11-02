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
use Symfony\Component\Messenger\Stamp\RetryStrategyStamp;

/**
 * @author Valtteri R <valtzu@gmail.com>
 */
final class DynamicRetryStrategy implements RetryStrategyInterface
{
    public function __construct(private RetryStrategyInterface $fallbackStrategy)
    {
    }

    public function isRetryable(Envelope $message, ?\Throwable $throwable = null): bool
    {
        return $message->last(RetryStrategyStamp::class)?->isRetryable()
            ?? $this->fallbackStrategy->isRetryable($message, $throwable);
    }

    public function getWaitingTime(Envelope $message, ?\Throwable $throwable = null): int
    {
        return $message->last(RetryStrategyStamp::class)?->getWaitingTime()
            ?? $this->fallbackStrategy->getWaitingTime($message, $throwable);
    }
}
