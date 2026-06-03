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
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
interface RateLimiterFactoryInterface
{
    /**
     * @param string|null $key an optional key used to identify the limiter
     */
    public function create(?string $key = null): LimiterInterface;
}
