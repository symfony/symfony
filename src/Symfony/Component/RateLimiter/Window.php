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
final class Window implements LimiterStateInterface
{
    private $id;
    private $hitCount = 0;
    private $intervalInSeconds;

    public function __construct(string $id, int $intervalInSeconds)
    {
        $this->id = $id;
        $this->intervalInSeconds = $intervalInSeconds;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getExpirationTime(): ?int
    {
        return $this->intervalInSeconds;
    }

    public function add(int $hits = 1)
    {
        $this->hitCount += $hits;
    }

    public function getHitCount(): int
    {
        return $this->hitCount;
    }

    /**
     * @internal
     */
    public function __sleep(): array
    {
        // $intervalInSeconds is not serialized, it should only be set
        // upon first creation of the Window.
        return ['id', 'hitCount'];
    }
}
