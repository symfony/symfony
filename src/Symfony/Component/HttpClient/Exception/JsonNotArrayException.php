<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Exception;

/**
 * Thrown by responses' toArray() method when the decoded JSON is not an array.
 *
 * Exposes the actual decoded value via getDecodedValue() so callers can branch on
 * its concrete PHP type (null, bool, int, float, string).
 */
final class JsonNotArrayException extends JsonException
{
    public function __construct(
        string $message,
        private readonly mixed $decodedValue,
    ) {
        parent::__construct($message);
    }

    public function getDecodedValue(): mixed
    {
        return $this->decodedValue;
    }
}
