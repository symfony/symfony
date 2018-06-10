<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Serialization;

use Symfony\Component\Messenger\Envelope;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 *
 * @experimental in 4.1
 */
interface EncoderInterface
{
    /**
     * Encodes an envelope content (message & items) to a common format understandable by transports.
     * The encoded array should only contain scalar and arrays.
     *
     * The most common keys of the encoded array are:
     * - `body` (string) - the message body
     * - `headers` (string<string>) - a key/value pair of headers
     *
     * @param Envelope $envelope The envelop containing the message put on the MessageBus by the user
     */
    public function encode(Envelope $envelope): array;
}
