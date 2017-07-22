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
 * Interface for adapters and simple cache implementations that allow pruning expired items.
 */
interface PruneableInterface
{
    /**
     * @return bool
     */
    public function prune();
}
