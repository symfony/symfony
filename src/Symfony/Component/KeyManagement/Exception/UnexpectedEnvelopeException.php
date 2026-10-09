<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Exception;

/**
 * Thrown when a payload is well formed, and authentic, but is not one the reader accepts.
 *
 * Unlike {@see DecryptionFailedException}, whose message says nothing on purpose, this one names what
 * was expected: the refusal happens before the payload is opened, and it tells an attacker nothing
 * it did not write itself. It is a value failure rather than a {@see LogicException}, since the value
 * is attacker controlled even when a misconfiguration is what raises it.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
class UnexpectedEnvelopeException extends RuntimeException
{
    /**
     * The payload refers to a data key other than the one the reader named: a scope, or a master key
     * id, whichever `encrypt()` was given.
     */
    public static function key(string $expected, string $found): self
    {
        return new self(\sprintf('The payload was written under "%s", while "%s" was expected.', $found, $expected));
    }
}
