<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Cache\Traits;

use Symphony\Component\Cache\PruneableInterface;
use Symphony\Component\Cache\ResettableInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
trait ProxyTrait
{
    private $pool;

    /**
     * {@inheritdoc}
     */
    public function prune()
    {
        return $this->pool instanceof PruneableInterface && $this->pool->prune();
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        if ($this->pool instanceof ResettableInterface) {
            $this->pool->reset();
        }
    }
}
