<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Handler;

use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * @deprecated since Symfony 8.2, dispatch the message wrapped in the RedispatchMessage with a TransportNamesStamp instead
 */
final class RedispatchMessageHandler
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(RedispatchMessage $message): mixed
    {
        trigger_deprecation('symfony/messenger', '8.2', 'Dispatching a "%s" to a bus is deprecated, dispatch the message it wraps with a "%s" instead.', RedispatchMessage::class, TransportNamesStamp::class);

        // no transport name means "use the senders configured for the message" instead of "use no sender"
        $transportNames = array_values(array_filter((array) $message->transportNames, static fn ($name): bool => '' !== $name));

        $envelope = $this->bus->dispatch($message->envelope, $transportNames ? [new TransportNamesStamp($transportNames)] : []);

        return $envelope->last(HandledStamp::class)?->getResult();
    }
}
