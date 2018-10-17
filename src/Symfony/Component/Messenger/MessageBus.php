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

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 * @author Matthias Noback <matthiasnoback@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class MessageBus implements MessageBusInterface
{
    private $middlewareAggregate;

    /**
     * @param MiddlewareInterface[]|iterable $middlewareHandlers
     */
    public function __construct(iterable $middlewareHandlers = array())
    {
        if ($middlewareHandlers instanceof \IteratorAggregate) {
            $this->middlewareAggregate = $middlewareHandlers;
        } elseif (\is_array($middlewareHandlers)) {
            $this->middlewareAggregate = new \ArrayObject($middlewareHandlers);
        } else {
            $this->middlewareAggregate = new \ArrayObject(iterator_to_array($middlewareHandlers, false));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function dispatch($message): void
    {
        if (!\is_object($message)) {
            throw new \TypeError(sprintf('Invalid argument provided to "%s()": expected object, but got %s.', __METHOD__, \gettype($message)));
        }

        $middlewareIterator = $this->middlewareAggregate->getIterator();

        foreach ($middlewareIterator as $middleware) {
            $currentEnvelope = Envelope::wrap($message);

            // Do not provide the envelope if the middleware cannot read it:
            $message = $middleware instanceof EnvelopeAwareInterface ? $currentEnvelope : $currentEnvelope->getMessage();

            $next = static function ($message) use ($middlewareIterator, &$currentEnvelope, &$next) {
                $middlewareIterator->next();

                if (!$middlewareIterator->valid()) {
                    return;
                }

                $middleware = $middlewareIterator->current();

                if ($message instanceof Envelope) {
                    $currentEnvelope = $message;
                } else {
                    $message = $currentEnvelope->withMessage($message);
                }

                if (!$middleware instanceof EnvelopeAwareInterface) {
                    $message = $message->getMessage();
                }

                $middleware->handle($message, $next);
            };

            $middleware->handle($message, $next);
        }
    }
}
