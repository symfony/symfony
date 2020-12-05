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
final class TokenBucket implements LimiterStateInterface
{
    private $id;
    private $rate;

    /**
     * @var int
     */
    private $tokens;

    /**
     * @var int
     */
    private $burstSize;

    /**
     * @var float
     */
    private $timer;

    /**
     * @param string     $id            unique identifier for this bucket
     * @param int        $initialTokens the initial number of tokens in the bucket (i.e. the max burst size)
     * @param Rate       $rate          the fill rate and time of this bucket
     * @param float|null $timer         the current timer of the bucket, defaulting to microtime(true)
     */
    public function __construct(string $id, int $initialTokens, Rate $rate, ?float $timer = null)
    {
        if ($initialTokens < 1) {
            throw new \InvalidArgumentException(sprintf('Cannot set the limit of "%s" to 0, as that would never accept any hit.', TokenBucketLimiter::class));
        }

        $this->id = $id;
        $this->tokens = $this->burstSize = $initialTokens;
        $this->rate = $rate;
        $this->timer = $timer ?? microtime(true);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setTimer(float $microtime): void
    {
        $this->timer = $microtime;
    }

    public function getTimer(): float
    {
        return $this->timer;
    }

    public function setTokens(int $tokens): void
    {
        $this->tokens = $tokens;
    }

    public function getAvailableTokens(float $now): int
    {
        $elapsed = $now - $this->timer;

        return min($this->burstSize, $this->tokens + $this->rate->calculateNewTokensDuringInterval($elapsed));
    }

    public function getExpirationTime(): int
    {
        return $this->rate->calculateTimeForTokens($this->burstSize);
    }

    /**
     * @internal
     */
    public function __sleep(): array
    {
        $this->stringRate = (string) $this->rate;

        return ['id', 'tokens', 'timer', 'burstSize', 'stringRate'];
    }

    /**
     * @internal
     */
    public function __wakeup(): void
    {
        $this->rate = Rate::fromString($this->stringRate);
        unset($this->stringRate);
    }
}
