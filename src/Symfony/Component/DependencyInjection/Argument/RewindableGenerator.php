<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Argument;

/**
 * @internal
 */
class RewindableGenerator implements \IteratorAggregate, \Countable
{
    private \Closure $generator;
    private \Closure|int $count;

    public function __construct(callable $generator, int|callable $count)
    {
        $this->generator = $generator(...);
        $this->count = \is_int($count) ? $count : $count(...);
    }

    public function getIterator(): \Traversable
    {
        $g = $this->generator;

        return $g();
    }

    public function count(): int
    {
        if (!\is_int($count = $this->count)) {
            $this->count = $count();
        }

        return $this->count;
    }
}
