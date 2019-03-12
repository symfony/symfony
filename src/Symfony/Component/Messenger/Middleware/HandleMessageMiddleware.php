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

use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 *
 * @experimental in 4.2
 */
class HandleMessageMiddleware implements MiddlewareInterface
{
    use LoggerAwareTrait;

    private $handlersLocator;
    private $allowNoHandlers;

    public function __construct(HandlersLocatorInterface $handlersLocator, bool $allowNoHandlers = false)
    {
        $this->handlersLocator = $handlersLocator;
        $this->allowNoHandlers = $allowNoHandlers;
        $this->logger = new NullLogger();
    }

    /**
     * {@inheritdoc}
     *
     * @throws NoHandlerForMessageException When no handler is found and $allowNoHandlers is false
     */
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $handler = null;
        $message = $envelope->getMessage();

        $context = [
            'message' => $message,
            'class' => \get_class($message),
        ];

        foreach ($this->handlersLocator->getHandlers($envelope) as $alias => $handler) {
            $handledStamp = HandledStamp::fromCallable($handler, $handler($message), \is_string($alias) ? $alias : null);
            $envelope = $envelope->with($handledStamp);
            $this->logger->info('Message "{class}" handled by "{handler}"', $context + ['handler' => $handledStamp->getCallableName()]);
        }

        if (null === $handler) {
            if (!$this->allowNoHandlers) {
                throw new NoHandlerForMessageException(sprintf('No handler for message "%s".', $context['class']));
            }

            $this->logger->info('No handler for message "{class}"', $context);
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
