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
 */
final class Window implements LimiterStateInterface
{
    private int $hitCount = 0;
    private int $maxSize;
    private float $timer;

    public function __construct(
        private string $id,
        private int $intervalInSeconds,
        int $windowSize,
        ?float $timer = null,
    ) {
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

    public function add(int $hits = 1, ?float $now = null): void
    {
        $now ??= microtime(true);
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

    public function getAvailableTokens(float $now): int
    {
        // if now is more than the window interval in the past, all tokens are available
        if (($now - $this->timer) > $this->intervalInSeconds) {
            return $this->maxSize;
        }

        return $this->maxSize - $this->hitCount;
    }

    public function calculateTimeForTokens(int $tokens, float $now): int
    {
        if (($this->maxSize - $this->hitCount) >= $tokens) {
            return 0;
        }

        return (int) ceil($this->timer + $this->intervalInSeconds - $now);
    }

    public function __serialize(): array
    {
        return [
            $this->id => $this->timer,
            pack('NN', $this->hitCount, $this->intervalInSeconds) => $this->maxSize,
        ];
    }

    public function __unserialize(array $data): void
    {
        // BC layer for old objects serialized via __sleep
        if (5 === \count($data)) {
            $data = array_values($data);
            $this->id = $data[0];
            $this->hitCount = $data[1];
            $this->intervalInSeconds = $data[2];
            $this->maxSize = $data[3];
            $this->timer = $data[4];

            return;
        }

        [$this->timer, $this->maxSize] = array_values($data);
        [$this->id, $pack] = array_keys($data);
        ['a' => $this->hitCount, 'b' => $this->intervalInSeconds] = unpack('Na/Nb', $pack);
    }
}
