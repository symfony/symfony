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

use Symfony\Component\RateLimiter\RateLimiterBuilder;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Configures a rate limiter that is created on the fly instead of being declared up front.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
interface PolicyInterface
{
    /**
     * @param string $id Unique identifier for the rate limiter; must stay stable across
     *                   requests so the same window or bucket keeps accumulating
     */
    public function createFactory(RateLimiterBuilder $builder, string $id): RateLimiterFactoryInterface;
}
