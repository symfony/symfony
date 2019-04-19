<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger;

use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\Pipeline;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 * @author Matthias Noback <matthiasnoback@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @experimental in 4.2
 */
class MessageBus implements MessageBusInterface
{
    private $middlewares;

    /**
     * @param MiddlewareInterface[]|iterable $middlewareHandlers
     */
    public function __construct(array $middlewares = [])
    {
        $this->middlewares = $middlewares;
    }

    /**
     * {@inheritdoc}
     */
    public function dispatch($message, array $stamps = []): Envelope
    {
        if (!\is_object($message)) {
            throw new \TypeError(sprintf('Invalid argument provided to "%s()": expected object, but got %s.', __METHOD__, \gettype($message)));
        }

        $envelope = Envelope::wrap($message, $stamps);
        $pipeline = new Pipeline($this->middlewares);

        return $pipeline->handle($envelope);
    }
}
