<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Cache;

/**
 * Interface extends psr-6 and psr-16 caches to allow for pruning (deletion) of all expired cache items.
 */
interface PruneableInterface
{
    /**
     * @return bool
     */
    public function prune();
}
