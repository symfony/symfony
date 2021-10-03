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
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use Symfony\Component\Messenger\Handler\OptionalHandlerLocator;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class HandleMessageMiddleware implements MiddlewareInterface
{
    use LoggerAwareTrait;

    private $handlersLocator;
    private $allowNoHandlers;

    public function __construct(HandlersLocatorInterface $handlersLocator, bool $allowNoHandlers = false)
    {
        if (1 < \func_num_args()) {
            trigger_deprecation('symfony/messenger', '5.4', 'Passing the allowNoHandlers argument is deprecated, inject an instance of the "%s" instead.', OptionalHandlerLocator::class);
        }
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

        $exceptions = [];
        foreach ($this->handlersLocator->getHandlers($envelope) as $handlerDescriptor) {
            if ($this->messageHasAlreadyBeenHandled($envelope, $handlerDescriptor)) {
                continue;
            }

            try {
                $handler = $handlerDescriptor->getHandler();
                $handledStamp = HandledStamp::fromDescriptor($handlerDescriptor, $handler($message));
                $envelope = $envelope->with($handledStamp);
                $this->logger->info('Message {class} handled by {handler}', $context + ['handler' => $handledStamp->getHandlerName()]);
            } catch (\Throwable $e) {
                $exceptions[] = $e;
            }
        }

        if (null === $handler) {
            if (!$this->allowNoHandlers) {
                throw new NoHandlerForMessageException(sprintf('No handler for message "%s".', $context['class']));
            }

            $this->logger->info('No handler for message {class}', $context);
        }

        if (\count($exceptions)) {
            throw new HandlerFailedException($envelope, $exceptions);
        }

        return $stack->next()->handle($envelope, $stack);
    }

    private function messageHasAlreadyBeenHandled(Envelope $envelope, HandlerDescriptor $handlerDescriptor): bool
    {
        $some = array_filter($envelope
            ->all(HandledStamp::class), function (HandledStamp $stamp) use ($handlerDescriptor) {
                return $stamp->getHandlerName() === $handlerDescriptor->getName();
            });

        return \count($some) > 0;
    }
}
