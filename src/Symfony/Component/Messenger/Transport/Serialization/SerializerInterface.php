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
 */
interface SerializerInterface
{
    /**
     * Decodes an envelope and its message from an encoded-form.
     *
     * The `$encodedEnvelope` parameter is a key-value array that
     * describes the envelope and its content, that will be used by the different transports.
     *
     * The most common keys are:
     * - `body` (string) - the message body
     * - `headers` (string<string>) - a key/value pair of headers
     */
    public function decode(array $encodedEnvelope): Envelope;

    /**
     * Encodes an envelope content (message & stamps) to a common format understandable by transports.
     * The encoded array should only contain scalars and arrays.
     *
     * The most common keys of the encoded array are:
     * - `body` (string) - the message body
     * - `headers` (string<string>) - a key/value pair of headers
     */
    public function encode(Envelope $envelope): array;
}
