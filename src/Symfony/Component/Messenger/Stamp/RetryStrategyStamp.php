<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Stamp;

/**
 * Override retry behavior for a single message when dispatching.
 *
 * @author Valtteri R <valtzu@gmail.com>
 */
final class RetryStrategyStamp implements StampInterface
{
    public function __construct(
        private ?bool $retryable = null,
        private ?int $waitingTime = null,
    ) {
    }

    public function isRetryable(): ?bool
    {
        return $this->retryable;
    }

    public function getWaitingTime(): ?int
    {
        return $this->waitingTime;
    }
}
