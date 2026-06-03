<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Exception;

use Symfony\Component\Messenger\Envelope;

/**
 * Thrown when a message cannot be decoded in a serializer.
 */
class MessageDecodingFailedException extends InvalidArgumentException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly array $encodedEnvelope = [],
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function wrap(array $encodedEnvelope, string $message, int $code = 0, ?\Throwable $previous = null): Envelope
    {
        return new Envelope(new self($message, $code, $previous, $encodedEnvelope));
    }
}
