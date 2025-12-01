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
use Symfony\Component\Messenger\Exception\ExceptionInterface;

/**
 * A sender that supports sending multiple envelopes in a single batch operation.
 *
 * @author Joppe De Cuyper <hello@joppe.dev>
 */
interface BatchSenderInterface extends SenderInterface
{
    /**
     * Sends multiple envelopes in a single batch operation.
     *
     * This method MUST be atomic: either all messages are sent successfully,
     * or none are sent and an exception is thrown.
     *
     * The returned envelopes should contain a TransportMessageIdStamp and
     * MUST be in the same order as the input envelopes.
     *
     * @param Envelope[] $envelopes
     *
     * @return Envelope[]
     *
     * @throws ExceptionInterface
     */
    public function sendBatch(array $envelopes): array;
}
