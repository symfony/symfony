<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Webhook;

/**
 * The Standard Webhooks signature scheme, shared by the sender and the receiver.
 *
 * @see https://www.standardwebhooks.com/
 *
 * @internal
 */
final class StandardWebhooksSignature
{
    public const PREFIX = 'v1,';

    public static function sign(string $id, string $timestamp, string $body, #[\SensitiveParameter] string $secret): string
    {
        return self::PREFIX.base64_encode(hash_hmac('sha256', $id.'.'.$timestamp.'.'.$body, self::key($secret), true));
    }

    /**
     * The specification serializes the secret as base64 behind a "whsec_" prefix.
     */
    private static function key(#[\SensitiveParameter] string $secret): string
    {
        if (!str_starts_with($secret, 'whsec_')) {
            return $secret;
        }

        return base64_decode(substr($secret, 6), true) ?: $secret;
    }
}
