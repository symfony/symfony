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
    private ?\DateTimeImmutable $anchor = null;

    public function __construct(
        private string $id,
        private int $maxSize,
        private \DateTimeImmutable $periodStart,
        private \DateTimeImmutable $periodEnd,
        private \DateInterval $interval,
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
        $periods = max(1, (int) ceil($this->hitCount / $this->maxSize));

        return max(0, $this->getPeriodBoundary($periods)->getTimestamp() - time());
    }

    public function configure(\DateInterval $interval, \DateTimeImmutable $anchor, float $now): void
    {
        $this->interval = $interval;
        $this->anchor = $anchor;
        $this->periodStart = $this->periodStart->setTimezone($anchor->getTimezone());
        $this->periodEnd = $this->periodEnd->setTimezone($anchor->getTimezone());

        while ($this->periodEnd->getTimestamp() <= $now) {
            $this->periodStart = $this->periodEnd;
            $this->periodEnd = $this->getPeriodBoundary(2);
            $this->hitCount = max(0, $this->hitCount - $this->maxSize);
        }
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

        $periods = (int) ceil(($this->hitCount + $tokens) / $this->maxSize) - 1;

        return max(0, (int) ceil($this->getPeriodBoundary($periods)->getTimestamp() - $now));
    }

    public function __serialize(): array
    {
        return [
            'i' => $this->id,
            'm' => $this->maxSize,
            'h' => $this->hitCount,
            's' => $this->periodStart->format('Y-m-d\TH:i:s.uP'),
            'e' => $this->periodEnd->format('Y-m-d\TH:i:s.uP'),
            'd' => $this->interval,
            'a' => $this->anchor?->format('Y-m-d\TH:i:s.uP'),
        ];
    }

    public function __unserialize(array $data): void
    {
        if (($data['i'] ?? null) instanceof \Stringable) {
            throw new \BadMethodCallException('Cannot unserialize '.self::class);
        }

        $this->id = $data['i'];
        $this->maxSize = $data['m'];
        $this->hitCount = $data['h'];
        $this->periodStart = new \DateTimeImmutable($data['s']);
        $this->periodEnd = new \DateTimeImmutable($data['e']);
        $this->interval = $data['d'] ?? $this->periodStart->diff($this->periodEnd);
        $this->anchor = isset($data['a']) ? new \DateTimeImmutable($data['a']) : null;
    }

    private function getPeriodBoundary(int $periods): \DateTimeImmutable
    {
        $boundary = $this->periodEnd;
        for ($i = 1; $i < $periods; ++$i) {
            if (null === $this->anchor || $boundary >= $this->anchor) {
                $boundary = $boundary->add($this->interval);
                continue;
            }

            // Adding a calendar interval does not necessarily undo subtracting it from a future anchor.
            $next = $this->anchor;
            while (($previous = $next->sub($this->interval)) > $boundary) {
                $next = $previous;
            }
            $boundary = $next;
        }

        return $boundary;
    }
}
