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
class ArraySelectorCache implements SelectorCacheInterface
{
    /**
     * @var array
     */
    private $cache = array();

    /**
     * {@inheritdoc}
     */
    public function save($key, $expr)
    {
        $this->cache[$key] = $expr;
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($key)
    {
        return isset($this->cache[$key]) ? $this->cache[$key] : null;
    }
}