<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Adapter;

use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;

/**
 * Interface for creating contextualized key spaces from existing pools.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface ContextAwareAdapterInterface extends AdapterInterface
{
    /**
     * Derivates a new cache pool from an existing one by contextualizing its key space.
     *
     * Contexts add to each others.
     * Clearing a parent also clears all its derivated
     * pools (but not necessarily their deferred items).
     *
     * @param string $context A context identifier.
     *
     * @return self A clone of the current instance, where cache keys are isolated from its parent
     *
     * @throws InvalidArgumentException When $context contains invalid characters
     * @throws CacheException           When the adapter can't be forked
     */
    public function withContext($context);
}
