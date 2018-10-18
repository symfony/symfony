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
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
interface MiddlewareInterface
{
    /**
     * @param callable|NextInterface $next
     */
    public function handle(Envelope $envelope, callable $next): void;
}

/**
 * @internal
 */
interface NextInterface
{
    public function __invoke(Envelope $envelope): void;
}
