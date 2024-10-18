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

use Psr\Clock\ClockInterface;

/**
 * @internal
 */
trait ClockTrait
{
    private ?ClockInterface $clock;

    /**
     * @internal
     */
    public function setClock(?ClockInterface $clock): void
    {
        $this->clock = $clock;
    }

    private function now(): float
    {
        return (float) ($this->clock?->now()->format('U.u') ?? microtime(true));
    }
}
