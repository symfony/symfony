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

use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @internal
 */
final class ChainAdapterCallback
{
    public readonly mixed $callback;
    public readonly ChainAdapter $pool;
    public bool $save = true;

    public function __construct(callable $callback, ChainAdapter $pool)
    {
        $this->callback = $callback;
        $this->pool = $pool;
    }

    public function __invoke(ItemInterface $item, bool &$save): mixed
    {
        $value = ($this->callback)($item, $save);
        $this->save = $save;

        return $value;
    }

    public function cancelSave(): void
    {
        $this->save = false;
    }

    public static function fromCallable(callable $callback): ?self
    {
        $callback = $callback instanceof \Closure ? (new \ReflectionFunction($callback))->getClosureThis() : $callback;

        return $callback instanceof self ? $callback : null;
    }
}
