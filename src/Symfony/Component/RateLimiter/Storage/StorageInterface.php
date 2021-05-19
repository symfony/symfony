<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\Storage;

use Symfony\Component\RateLimiter\LimiterStateInterface;

/**
 * @experimental in 5.3
 */
interface StorageInterface
{
    public function save(LimiterStateInterface $limiterState): void;

    public function fetch(string $limiterStateId): ?LimiterStateInterface;

    public function delete(string $limiterStateId): void;
}
