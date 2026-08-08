<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement;

/**
 * URL-safe base64 helpers (RFC 4648 section 5): `+`/`/` of standard base64 are
 * replaced by `-`/`_` and the trailing `=` padding is dropped.
 *
 * Encoding is strictly URL-safe and unpadded. Decoding is permissive on
 * purpose: it accepts URL-safe input (`-`/`_`, with or without padding) AND
 * standard base64 (`+`/`/`, with or without padding), so a value produced by
 * `base64_encode()` round-trips through {@see decode()} too. This is safe
 * because the standard alphabet (`+`, `/`) doesn't overlap with the URL-safe
 * alphabet (`-`, `_`), so the `strtr` is idempotent for either encoding.
 * `base64_decode(..., strict: true)` rejects unknown characters and accepts
 * unpadded inputs natively, so no re-padding is needed. It does let whitespace
 * through, which this codec inherits: a key pasted across two lines still
 * decodes, while anything else invalid returns `false`.
 *
 * Public because the bridges are separate packages that need it: Azure Key
 * Vault speaks this encoding for every value it exchanges, Google Cloud KMS
 * needs it to assemble the JWT it authenticates with, and the local factories
 * decode key material out of a DSN. Sharing one implementation keeps the
 * permissive decode above from drifting between three packages.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class Base64UrlSafe
{
    public static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function decode(string $value): string|false
    {
        return base64_decode(strtr($value, '-_', '+/'), true);
    }
}
