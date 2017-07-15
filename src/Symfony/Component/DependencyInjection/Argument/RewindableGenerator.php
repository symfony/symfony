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

use Psr\Container\ContainerInterface;

/**
 * @internal
 */
class RewindableGenerator implements ContainerInterface, \IteratorAggregate, \Countable
{
    private $factory;
    private $generator;
    private $count;

    /**
     * @param callable     $factory
     * @param callable     $generator
     * @param int|callable $count
     */
    public function __construct(callable $factory, callable $generator, $count)
    {
        $this->factory = $factory;
        $this->generator = $generator;
        $this->count = $count;
    }

    public function has($id)
    {
        $factory = $this->factory;

        return $factory($id, true);
    }

    public function get($id)
    {
        $factory = $this->factory;

        return $factory($id);
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
