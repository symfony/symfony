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
 * Puts a pool in "refresh mode".
 *
 * While in this mode, reading a key reports a miss the first time, so that its value is
 * recomputed and saved instead of being served from the backend. Contrary to clearing a
 * pool, the previous values keep being served to everybody else until the new ones are
 * saved, so that there is never a window where the cache is cold.
 *
 * Each key is refreshed at most once per cycle: reading a key that was already refreshed
 * behaves normally, so that a value used several times is not recomputed every time.
 *
 * Pools that are also resettable turn the mode off on reset, so that it cannot leak into
 * the next request of a worker runtime.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
interface RefreshableInterface
{
    public function enableRefresh(bool $enable = true): void;
}
