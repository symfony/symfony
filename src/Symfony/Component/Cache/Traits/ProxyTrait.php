<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Traits;

use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\ResettableInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
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
