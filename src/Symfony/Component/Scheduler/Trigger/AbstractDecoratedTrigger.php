<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Trigger;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
abstract class AbstractDecoratedTrigger implements TriggerInterface
{
    public function __construct(private TriggerInterface $inner)
    {
    }

    final public function inner(): TriggerInterface
    {
        $inner = $this->inner;

        while ($inner instanceof self) {
            $inner = $inner->inner;
        }

        return $inner;
    }

    /**
     * @return \Traversable<self>
     */
    final public function decorators(): \Traversable
    {
        yield $this;

        $inner = $this->inner;

        while ($inner instanceof self) {
            yield $inner;

            $inner = $inner->inner;
        }
    }
}
