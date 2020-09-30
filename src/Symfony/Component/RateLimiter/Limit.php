<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter;

/**
 * @author Valentin Silvestre <vsilvestre.pro@gmail.com>
 *
 * @experimental in 5.2
 */
class Limit
{
    private $availableTokens;
    private $retryAfter;
    private $accepted;

    public function __construct(int $availableTokens, \DateTimeImmutable $retryAfter, bool $accepted)
    {
        $this->availableTokens = $availableTokens;
        $this->retryAfter = $retryAfter;
        $this->accepted = $accepted;
    }

    public function isAccepted(): bool
    {
        return $this->accepted;
    }

    public function getRetryAfter(): \DateTimeImmutable
    {
        return $this->retryAfter;
    }

    public function getRemainingTokens(): int
    {
        return $this->availableTokens;
    }

    public function wait(): void
    {
        sleep(($this->retryAfter->getTimestamp() - time()) * 1e6);
    }
}
