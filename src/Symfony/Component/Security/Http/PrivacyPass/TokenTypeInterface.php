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
 * An issuance protocol, which determines the structure of a token and the key it is verified with.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9577#section-6.2
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
interface TokenTypeInterface
{
    /**
     * The 2-octet identifier of the protocol, as registered in the "Privacy Pass Token Types" registry.
     */
    public function getValue(): int;

    /**
     * The length of the key identifier a token carries, in bytes (Nid).
     */
    public function getKeyIdLength(): int;

    /**
     * The length of the authenticator a token carries, in bytes (Nk).
     */
    public function getAuthenticatorLength(): int;

    /**
     * @param string $tokenKey The base64url-encoded public key, as the "token-key" authentication parameter and the issuer directory carry it
     *
     * @throws \InvalidArgumentException If the key is not one a token of this type can be verified with
     */
    public function createTokenKey(string $tokenKey): TokenKeyInterface;
}
