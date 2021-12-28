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

use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Leverages a message bus to expect a single, synchronous message handling and return its result.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
trait HandleTrait
{
    /** @var MessageBusInterface */
    private $messageBus;

    /**
     * Dispatches the given message, expecting to be handled by a single handler
     * and returns the result from the handler returned value.
     * This behavior is useful for both synchronous command & query buses,
     * the last one usually returning the handler result.
     *
     * @param object|Envelope $message The message or the message pre-wrapped in an envelope
     *
     * @return mixed
     */
    private function handle(object $message)
    {
        if (!$this->messageBus instanceof MessageBusInterface) {
            throw new LogicException(sprintf('You must provide a "%s" instance in the "%s::$messageBus" property, "%s" given.', MessageBusInterface::class, static::class, get_debug_type($this->messageBus)));
        }

        $envelope = $this->messageBus->dispatch($message);
        /** @var HandledStamp[] $handledStamps */
        $handledStamps = $envelope->all(HandledStamp::class);

        if (!$handledStamps) {
            throw new LogicException(sprintf('Message of type "%s" was handled zero times. Exactly one handler is expected when using "%s::%s()".', get_debug_type($envelope->getMessage()), static::class, __FUNCTION__));
        }

        if (\count($handledStamps) > 1) {
            $handlers = implode(', ', array_map(function (HandledStamp $stamp): string {
                return sprintf('"%s"', $stamp->getHandlerName());
            }, $handledStamps));

            throw new LogicException(sprintf('Message of type "%s" was handled multiple times. Only one handler is expected when using "%s::%s()", got %d: %s.', get_debug_type($envelope->getMessage()), static::class, __FUNCTION__, \count($handledStamps), $handlers));
        }

        return $handledStamps[0]->getResult();
    }
}
