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

use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * A message bus that supports batch dispatching.
 *
 * @author Joppe De Cuyper <hello@joppe.dev>
 */
interface BatchMessageBusInterface extends MessageBusInterface
{
    /**
     * Dispatches multiple messages in a batch, allowing transports to optimize delivery.
     *
     * Each message passes through the full middleware chain individually.
     * The actual transport send is deferred until all messages are processed,
     * then executed as a single batch operation.
     *
     * @param object[]|Envelope[] $messages Messages or pre-wrapped envelopes
     * @param StampInterface[]    $stamps   Stamps applied to all messages
     *
     * @return Envelope[] In same order as input, with TransportMessageIdStamp added
     *
     * @throws ExceptionInterface
     */
    public function dispatchBatch(array $messages, array $stamps = []): array;
}
