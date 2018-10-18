<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Middleware\Enhancers;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;

/**
 * Execute the inner middleware according to an activation strategy.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class ActivationMiddlewareDecorator implements MiddlewareInterface
{
    private $inner;
    private $activated;

    /**
     * @param bool|callable $activated
     */
    public function __construct(MiddlewareInterface $inner, $activated)
    {
        $this->inner = $inner;
        $this->activated = $activated;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Envelope $envelope, callable $next): void
    {
        if (\is_callable($this->activated) ? ($this->activated)($envelope) : $this->activated) {
            $this->inner->handle($envelope, $next);
        } else {
            $next($envelope);
        }
    }
}
