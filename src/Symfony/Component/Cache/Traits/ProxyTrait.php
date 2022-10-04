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
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
trait ProxyTrait
{
    private object $pool;

    public function prune(): bool
    {
        return $this->pool instanceof PruneableInterface && $this->pool->prune();
    }

    public function reset()
    {
        if ($this->pool instanceof ResetInterface) {
            $this->pool->reset();
        }
    }
}
