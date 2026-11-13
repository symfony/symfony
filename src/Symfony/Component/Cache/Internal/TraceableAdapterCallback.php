<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Internal;

use Symfony\Component\Cache\Adapter\TraceableAdapter;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @internal
 */
final class TraceableAdapterCallback
{
    public readonly mixed $callback;
    public readonly TraceableAdapter $pool;
    public bool $isHit = true;

    public function __construct(callable $callback, TraceableAdapter $pool)
    {
        $this->callback = $callback;
        $this->pool = $pool;
    }

    public function __invoke(ItemInterface $item, bool &$save): mixed
    {
        $this->isHit = $item->isHit();

        return ($this->callback)($item, $save);
    }
}
