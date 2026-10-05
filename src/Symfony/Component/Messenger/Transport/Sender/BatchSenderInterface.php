<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Sender;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\BatchSendFailedException;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

/**
 * A sender that sends several envelopes with as few requests as its transport allows.
 *
 * @author Joppe De Cuyper <hello@joppe.dev>
 */
interface BatchSenderInterface extends SenderInterface
{
    /**
     * Sends the envelopes, split in as many requests as the transport requires.
     *
     * The returned envelopes keep the keys of the given ones. A transport that cannot tell the id of each message does not add any TransportMessageIdStamp.
     *
     * @param non-empty-array<Envelope> $envelopes
     *
     * @return array<Envelope>
     *
     * @throws BatchSendFailedException When some envelopes could not be sent, to tell which ones were
     * @throws ExceptionInterface       When none of the envelopes was sent
     */
    public function sendBatch(array $envelopes): array;
}
