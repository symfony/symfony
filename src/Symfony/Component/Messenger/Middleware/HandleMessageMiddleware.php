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
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\Handler\Acknowledger;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use Symfony\Component\Messenger\Stamp\AckStamp;
use Symfony\Component\Messenger\Stamp\FlushBatchHandlersStamp;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\HandlerArgumentsStamp;
use Symfony\Component\Messenger\Stamp\NoAutoAckStamp;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class HandleMessageMiddleware implements MiddlewareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private HandlersLocatorInterface $handlersLocator,
        private bool $allowNoHandlers = false,
    ) {
    }

    /**
     * @throws NoHandlerForMessageException When no handler is found and $allowNoHandlers is false
     */
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $handler = null;
        $message = $envelope->getMessage();

        $context = [
            'class' => $message::class,
        ];

        $exceptions = [];
        $alreadyHandled = false;
        foreach ($this->handlersLocator->getHandlers($envelope) as $handlerDescriptor) {
            if ($this->messageHasAlreadyBeenHandled($envelope, $handlerDescriptor)) {
                $alreadyHandled = true;
                continue;
            }

            try {
                $handler = $handlerDescriptor->getHandler();
                $batchHandler = $handlerDescriptor->getBatchHandler();

                /** @var AckStamp $ackStamp */
                if ($batchHandler && $ackStamp = $envelope->last(AckStamp::class)) {
                    $ack = new Acknowledger(get_debug_type($batchHandler), static function (?\Throwable $e = null, $result = null) use ($envelope, $ackStamp, $handlerDescriptor) {
                        if (null !== $e) {
                            $e = new HandlerFailedException($envelope, [$handlerDescriptor->getName() => $e]);
                        } else {
                            $envelope = $envelope->with(HandledStamp::fromDescriptor($handlerDescriptor, $result));
                        }

                        $ackStamp->ack($envelope, $e);
                    });

                    $result = $this->callHandler($handler, $message, $ack, $envelope->last(HandlerArgumentsStamp::class));

                    if (!\is_int($result) || 0 > $result) {
                        throw new LogicException(sprintf('A handler implementing BatchHandlerInterface must return the size of the current batch as a positive integer, "%s" returned from "%s".', \is_int($result) ? $result : get_debug_type($result), get_debug_type($batchHandler)));
                    }

                    if (!$ack->isAcknowledged()) {
                        $envelope = $envelope->with(new NoAutoAckStamp($handlerDescriptor));
                    } elseif ($ack->getError()) {
                        throw $ack->getError();
                    } else {
                        $result = $ack->getResult();
                    }
                } else {
                    $result = $this->callHandler($handler, $message, null, $envelope->last(HandlerArgumentsStamp::class));
                }

                $handledStamp = HandledStamp::fromDescriptor($handlerDescriptor, $result);
                $envelope = $envelope->with($handledStamp);
                $this->logger?->info('Message {class} handled by {handler}', $context + ['handler' => $handledStamp->getHandlerName()]);
            } catch (\Throwable $e) {
                $exceptions[$handlerDescriptor->getName()] = $e;
            }
        }

        /** @var FlushBatchHandlersStamp $flushStamp */
        if ($flushStamp = $envelope->last(FlushBatchHandlersStamp::class)) {
            /** @var NoAutoAckStamp $stamp */
            foreach ($envelope->all(NoAutoAckStamp::class) as $stamp) {
                try {
                    $handler = $stamp->getHandlerDescriptor()->getBatchHandler();
                    $handler->flush($flushStamp->force());
                } catch (\Throwable $e) {
                    $exceptions[$stamp->getHandlerDescriptor()->getName()] = $e;
                }
            }
        }

        if (null === $handler && !$alreadyHandled) {
            if (!$this->allowNoHandlers) {
                throw new NoHandlerForMessageException(sprintf('No handler for message "%s".', $context['class']));
            }

            $this->logger?->info('No handler for message {class}', $context);
        }

        if (\count($exceptions)) {
            throw new HandlerFailedException($envelope, $exceptions);
        }

        return $stack->next()->handle($envelope, $stack);
    }

    private function messageHasAlreadyBeenHandled(Envelope $envelope, HandlerDescriptor $handlerDescriptor): bool
    {
        /** @var HandledStamp $stamp */
        foreach ($envelope->all(HandledStamp::class) as $stamp) {
            if ($stamp->getHandlerName() === $handlerDescriptor->getName()) {
                return true;
            }
        }

        return false;
    }

    private function callHandler(callable $handler, object $message, ?Acknowledger $ack, ?HandlerArgumentsStamp $handlerArgumentsStamp): mixed
    {
        $arguments = [$message];
        if (null !== $ack) {
            $arguments[] = $ack;
        }
        if (null !== $handlerArgumentsStamp) {
            $arguments = [...$arguments, ...$handlerArgumentsStamp->getAdditionalArguments()];
        }

        return $handler(...$arguments);
    }
}
