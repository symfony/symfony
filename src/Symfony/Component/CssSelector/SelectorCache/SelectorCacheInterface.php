<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\SelectorCache;

/**
 * @author Diego Saint Esteben <diego@saintesteben.me>
 */
interface SelectorCacheInterface
{
    /**
     * Saves an XPath expression in the cache.
     *
     * @param string $key  The cache key
     * @param string $expr A XPath expression.
     */
    public function save($key, $expr);

    /**
     * Fetches an XPath expression from the cache.
     *
     * @param string $key The cache key
     *
     * @return string|null
     */
    public function fetch($key);
}
