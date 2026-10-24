<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\PrivacyPass;

/**
 * Token type 0x0002, "Blind RSA (2048-bit)".
 *
 * Its tokens are publicly verifiable: an origin verifies them with the issuer public key alone.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9578#section-8.2.2
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class BlindRsaTokenType implements TokenTypeInterface
{
    public function getValue(): int
    {
        return 0x0002;
    }

    public function getKeyIdLength(): int
    {
        return 32;
    }

    public function getAuthenticatorLength(): int
    {
        return 256;
    }

    public function createTokenKey(string $tokenKey): TokenKeyInterface
    {
        if (false === $decoded = base64_decode(strtr($tokenKey, '-_', '+/'), true)) {
            throw new \InvalidArgumentException('The token key is not base64url-encoded.');
        }

        return new BlindRsaTokenKey($decoded);
    }
}
