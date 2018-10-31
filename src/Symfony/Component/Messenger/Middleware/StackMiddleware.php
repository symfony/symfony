<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Middleware;

use Symfony\Component\Messenger\Envelope;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @experimental in 4.2
 */
class StackMiddleware implements MiddlewareInterface, StackInterface
{
    private $middlewareIterator;

    public function __construct(\Iterator $middlewareIterator = null)
    {
        $this->middlewareIterator = $middlewareIterator;
    }

    public function next(): MiddlewareInterface
    {
        if (null === $iterator = $this->middlewareIterator) {
            return $this;
        }
        $iterator->next();

        if (!$iterator->valid()) {
            $this->middlewareIterator = null;

            return $this;
        }

        return $iterator->current();
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        return $envelope;
    }
}
