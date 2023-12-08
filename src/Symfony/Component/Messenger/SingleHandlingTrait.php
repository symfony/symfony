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

use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;

trait SingleHandlingTrait
{
    private readonly MessageBusInterface $messageBus;

    /**
     * Dispatches the given message, expecting to be handled by a single handler
     * and returns the result from the handler returned value.
     * This behavior is useful for both synchronous command & query buses,
     * the last one usually returning the handler result.
     *
     * @param object|Envelope $message The message or the message pre-wrapped in an envelope
     * @param StampInterface[] $stamps
     */
    private function handle(object $message, array $stamps = []): mixed
    {
        if (!isset($this->messageBus)) {
            throw new LogicException(sprintf('You must provide a "%s" instance in the "%s::$messageBus" property, but that property has not been initialized yet.', MessageBusInterface::class, static::class));
        }

        $exceptions = [];

        try {
            $envelope = $this->messageBus->dispatch($message, $stamps);
        } catch (HandlerFailedException $exception) {
            $envelope = $exception->getEnvelope();
            $exceptions = $exception->getWrappedExceptions();
        }

        /** @var HandledStamp[] $handledStamps */
        $handledStamps = $envelope->all(HandledStamp::class);

        $handlers = array_merge(
            array_map(static fn (HandledStamp $stamp) => $stamp->getHandlerName(), $handledStamps),
            array_keys($exceptions),
        );

        if (!$handlers) {
            throw new LogicException(sprintf('Message of type "%s" was handled zero times. Exactly one handler is expected when using "%s::%s()".', $envelope->getMessage()::class, static::class, __FUNCTION__));
        }

        if (\count($handlers) > 1) {
            throw new LogicException(sprintf('Message of type "%s" was handled multiple times. Only one handler is expected when using "%s::%s()", got %d: "%s".', $envelope->getMessage()::class, static::class, __FUNCTION__, \count($handlers), implode('", "', $handlers)));
        }

        if ($exceptions) {
            throw reset($exceptions);
        }

        return $handledStamps[0]->getResult();
    }
}
