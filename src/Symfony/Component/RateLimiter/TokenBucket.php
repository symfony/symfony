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
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @experimental in 5.2
 */
final class TokenBucket implements LimiterStateInterface
{
    private $id;
    private $tokens;
    private $burstSize;
    private $rate;
    private $timer;

    /**
     * @param string     $id            unique identifier for this bucket
     * @param int        $initialTokens the initial number of tokens in the bucket (i.e. the max burst size)
     * @param Rate       $rate          the fill rate and time of this bucket
     * @param float|null $timer         the current timer of the bucket, defaulting to microtime(true)
     */
    public function __construct(string $id, int $initialTokens, Rate $rate, ?float $timer = null)
    {
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

    public function serialize(): string
    {
        return serialize([$this->id, $this->tokens, $this->timer, $this->burstSize, (string) $this->rate]);
    }

    public function unserialize($serialized): void
    {
        [$this->id, $this->tokens, $this->timer, $this->burstSize, $rate] = unserialize($serialized);

        $this->rate = Rate::fromString($rate);
    }
}
