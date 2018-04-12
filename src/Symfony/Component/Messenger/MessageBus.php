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

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 * @author Matthias Noback <matthiasnoback@gmail.com>
 */
class MessageBus implements MessageBusInterface
{
    private $middlewares;

    /**
     * @var MiddlewareInterface[]|null
     */
    private $indexedMiddlewares;

    /**
     * @param MiddlewareInterface[]|iterable $middlewares
     */
    public function __construct(iterable $middlewares = array())
    {
        $this->middlewares = $middlewares;
    }

    /**
     * {@inheritdoc}
     */
    public function dispatch($message)
    {
        return \call_user_func($this->callableForNextMiddleware(0), $message);
    }

    private function callableForNextMiddleware(int $index): callable
    {
        if (null === $this->indexedMiddlewares) {
            $this->indexedMiddlewares = \is_array($this->middlewares) ? array_values($this->middlewares) : iterator_to_array($this->middlewares, false);
        }

        if (!isset($this->indexedMiddlewares[$index])) {
            return function () {};
        }

        $middleware = $this->indexedMiddlewares[$index];

        return function ($message) use ($middleware, $index) {
            return $middleware->handle($message, $this->callableForNextMiddleware($index + 1));
        };
    }
}
