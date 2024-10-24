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

use Psr\Clock\ClockInterface;
use Symfony\Component\RateLimiter\ClockTrait;
use Symfony\Component\RateLimiter\LimiterStateInterface;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @internal
 */
final class TokenBucket implements LimiterStateInterface
{
    use ClockTrait;

    private int $tokens;
    private int $burstSize;
    private float $timer;

    /**
     * @param string     $id            unique identifier for this bucket
     * @param int        $initialTokens the initial number of tokens in the bucket (i.e. the max burst size)
     * @param Rate       $rate          the fill rate and time of this bucket
     * @param float|null $timer         the current timer of the bucket, defaulting to the current time in microseconds
     */
    public function __construct(
        private string $id,
        int $initialTokens,
        private Rate $rate,
        ?ClockInterface $clock = null,
        ?float $timer = null,
    ) {
        if ($initialTokens < 1) {
            throw new \InvalidArgumentException(\sprintf('Cannot set the limit of "%s" to 0, as that would never accept any hit.', TokenBucketLimiter::class));
        }

        $this->tokens = $this->burstSize = $initialTokens;
        $this->setClock($clock);
        $this->timer = $timer ?? $this->now();
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
        $elapsed = max(0, $now - $this->timer);
        $newTokens = $this->rate->calculateNewTokensDuringInterval($elapsed);

        if ($newTokens > 0) {
            $this->timer += $this->rate->calculateRefillInterval($elapsed);
        }

        return min($this->burstSize, $this->tokens + $newTokens);
    }

    public function getExpirationTime(): int
    {
        return $this->rate->calculateTimeForTokens($this->burstSize);
    }

    public function __serialize(): array
    {
        return [
            pack('N', $this->burstSize).$this->id => $this->tokens,
            (string) $this->rate => $this->timer,
        ];
    }

    public function __unserialize(array $data): void
    {
        // BC layer for old objects serialized via __sleep
        if (5 === \count($data)) {
            $data = array_values($data);
            $this->id = $data[0];
            $this->tokens = $data[1];
            $this->timer = $data[2];
            $this->burstSize = $data[3];
            $this->rate = Rate::fromString($data[4]);

            return;
        }

        [$this->tokens, $this->timer] = array_values($data);
        [$pack, $rate] = array_keys($data);
        $this->rate = Rate::fromString($rate);
        $this->burstSize = unpack('Na', $pack)['a'];
        $this->id = substr($pack, 4);
    }
}
