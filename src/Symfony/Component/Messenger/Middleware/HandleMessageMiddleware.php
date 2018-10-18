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
use Symfony\Component\Messenger\Handler\Locator\HandlerLocatorInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class HandleMessageMiddleware implements MiddlewareInterface
{
    private $messageHandlerResolver;

    public function __construct(HandlerLocatorInterface $messageHandlerResolver)
    {
        $this->messageHandlerResolver = $messageHandlerResolver;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Envelope $envelope, callable $next): void
    {
        $message = $envelope->getMessage();
        $handler = $this->messageHandlerResolver->resolve($message);
        $handler($message);

        $next($envelope);
    }
}
