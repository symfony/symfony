<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\Argument;

/**
 * @internal
 */
class RewindableGenerator implements \IteratorAggregate, \Countable
{
    private $generator;
    private $count;

    /**
     * @param callable     $generator
     * @param int|callable $count
     */
    public function __construct(callable $generator, $count)
    {
        $this->generator = $generator;
        $this->count = $count;
    }

    public function getIterator()
    {
        $g = $this->generator;

        return $g();
    }

    public function count()
    {
        if (is_callable($count = $this->count)) {
            $this->count = $count();
        }

        return $this->count;
    }
}
