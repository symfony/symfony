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
 * State of a fixed window aligned to calendar boundaries.
 *
 * @internal
 */
final class CalendarAlignedWindow implements LimiterStateInterface
{
    private int $hitCount = 0;

    public function __construct(
        private string $id,
        private int $maxSize,
        private \DateTimeImmutable $periodStart,
        private \DateTimeImmutable $periodEnd,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getPeriodStart(): \DateTimeImmutable
    {
        return $this->periodStart;
    }

    public function getPeriodEnd(): \DateTimeImmutable
    {
        return $this->periodEnd;
    }

    public function getExpirationTime(): ?int
    {
        return max(0, $this->periodEnd->getTimestamp() - time());
    }

    public function add(int $hits = 1, ?float $now = null): void
    {
        $this->hitCount = max(0, $this->hitCount + $hits);
    }

    public function getHitCount(): int
    {
        return $this->hitCount;
    }

    public function getAvailableTokens(float $now): int
    {
        return $this->maxSize - $this->hitCount;
    }

    public function calculateTimeForTokens(int $tokens, float $now): int
    {
        if (($this->maxSize - $this->hitCount) >= $tokens) {
            return 0;
        }

        return max(0, (int) ceil($this->periodEnd->getTimestamp() - $now));
    }

    public function __serialize(): array
    {
        return [
            'i' => $this->id,
            'm' => $this->maxSize,
            'h' => $this->hitCount,
            's' => $this->periodStart->format(\DateTimeInterface::RFC3339),
            'e' => $this->periodEnd->format(\DateTimeInterface::RFC3339),
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->id = $data['i'];
        $this->maxSize = $data['m'];
        $this->hitCount = $data['h'];
        $this->periodStart = new \DateTimeImmutable($data['s']);
        $this->periodEnd = new \DateTimeImmutable($data['e']);
    }
}
