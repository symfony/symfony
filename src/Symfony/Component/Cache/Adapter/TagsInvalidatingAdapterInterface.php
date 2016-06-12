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

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

/**
 * Interface for invalidating cached items using tag hierarchies.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface TagsInvalidatingAdapterInterface extends CacheItemPoolInterface
{
    /**
     * Invalidates cached items using tag hierarchies.
     *
     * @param string|string[] $tags A tag or an array of tag hierarchies to invalidate.
     *
     * @return bool True on success.
     *
     * @throws InvalidArgumentException When $tags is not valid.
     */
    public function invalidateTags($tags);
}
