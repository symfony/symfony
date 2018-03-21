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

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 *
 * @experimental in 4.1
 */
interface DecoderInterface
{
    /**
     * Decodes the message from an encoded-form.
     *
     * The `$encodedMessage` parameter is a key-value array that
     * describes the message, that will be used by the different adapters.
     *
     * The most common keys are:
     * - `body` (string) - the message body
     * - `headers` (string<string>) - a key/value pair of headers
     *
     * @return object
     */
    public function decode(array $encodedMessage);
}
