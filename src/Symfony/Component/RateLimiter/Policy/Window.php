<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\Policy;

use Symfony\Component\RateLimiter\LimiterStateInterface;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @internal
 * @experimental in 5.3
 */
final class Window implements LimiterStateInterface
{
    private $id;
    private $hitCount = 0;
    private $intervalInSeconds;
    private $maxSize;

    /**
     * @var float
     */
    private $timer;

    public function __construct(string $id, int $intervalInSeconds, int $windowSize, ?float $timer = null)
    {
        $this->id = $id;
        $this->intervalInSeconds = $intervalInSeconds;
        $this->maxSize = $windowSize;
        $this->timer = $timer ?? microtime(true);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getExpirationTime(): ?int
    {
        return $this->intervalInSeconds;
    }

    public function add(int $hits = 1, ?float $now = null)
    {
        $now = $now ?? microtime(true);
        if (($now - $this->timer) > $this->intervalInSeconds) {
            // reset window
            $this->timer = $now;
            $this->hitCount = 0;
        }

        $this->hitCount += $hits;
    }

    public function getHitCount(): int
    {
        return $this->hitCount;
    }

    public function getAvailableTokens(float $now)
    {
        // if timer is in future, there are no tokens available anymore
        if ($this->timer > $now) {
            return 0;
        }

        // if now is more than the window interval in the past, all tokens are available
        if (($now - $this->timer) > $this->intervalInSeconds) {
            return $this->maxSize;
        }

        return $this->maxSize - $this->hitCount;
    }

    public function calculateTimeForTokens(int $tokens): int
    {
        if (($this->maxSize - $this->hitCount) >= $tokens) {
            return 0;
        }

        $cyclesRequired = ceil($tokens / $this->maxSize);

        return $cyclesRequired * $this->intervalInSeconds;
    }
}
