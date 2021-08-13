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
 * Interface extends psr-6 and psr-16 caches to allow for pruning (deletion) of all expired cache items.
 */
interface PruneableInterface
{
    public function prune(): bool;
}
