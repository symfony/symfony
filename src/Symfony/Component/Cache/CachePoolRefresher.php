<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache;

/**
 * Puts every refreshable pool of the application in refresh mode at once.
 *
 * Inject a single pool instead when only that one should be refreshed.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class CachePoolRefresher implements RefreshableInterface
{
    /**
     * @param iterable<RefreshableInterface> $pools
     */
    public function __construct(
        private iterable $pools,
    ) {
    }

    public function enableRefresh(bool $enable = true): void
    {
        foreach ($this->pools as $pool) {
            $pool->enableRefresh($enable);
        }
    }

    /**
     * Refresh every pool while the callback runs, then leave refresh mode.
     *
     * @template T
     *
     * @param-immediately-invoked-callable $callback
     *
     * @param callable():T $callback
     *
     * @return T
     */
    public function runWithRefresh(callable $callback): mixed
    {
        $this->enableRefresh();

        try {
            return $callback();
        } finally {
            $this->enableRefresh(false);
        }
    }
}
