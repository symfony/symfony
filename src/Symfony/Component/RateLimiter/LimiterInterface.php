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
interface LimiterInterface
{
    /**
     * Use this method if you intend to drop if the required number
     * of tokens is unavailable.
     *
     * @param int $tokens the number of tokens required
     */
    public function consume(int $tokens = 1): Limit;

    /**
     * Resets the limit.
     */
    public function reset(): void;
}
