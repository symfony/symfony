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
 * Execute the inner middleware according to an activation strategy.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class ActivationMiddleware implements MiddlewareInterface
{
    private \Closure|bool $activated;

    public function __construct(
        private MiddlewareInterface $inner,
        bool|callable $activated,
    ) {
        $this->activated = \is_bool($activated) ? $activated : $activated(...);
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (\is_callable($this->activated) ? ($this->activated)($envelope) : $this->activated) {
            return $this->inner->handle($envelope, $stack);
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
