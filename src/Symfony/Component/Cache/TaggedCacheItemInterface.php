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

use Psr\Cache\CacheItemInterface;
use Psr\Cache\InvalidArgumentException;

/**
 * Interface for adding tags to cache items.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface TaggedCacheItemInterface extends CacheItemInterface
{
    /**
     * Adds a tag to a cache item.
     *
     * @param string|string[] $tags A tag or array of tags
     *
     * @return static
     *
     * @throws InvalidArgumentException When $tag is not valid.
     */
    public function tag($tags);
}
