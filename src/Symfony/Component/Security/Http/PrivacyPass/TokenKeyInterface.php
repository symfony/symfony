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
 * The public key of an issuer, which a token names and is verified with.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
interface TokenKeyInterface
{
    /**
     * The identifier a token names this key by.
     */
    public function getId(): string;

    /**
     * The value of the "token-key" authentication parameter, base64url-encoded with its padding (RFC 9577, Section 2.1.2).
     */
    public function toBase64Url(): string;

    /**
     * Whether the authenticator of a token is a valid signature of its input.
     */
    public function verify(string $authenticatorInput, string $authenticator): bool;
}
