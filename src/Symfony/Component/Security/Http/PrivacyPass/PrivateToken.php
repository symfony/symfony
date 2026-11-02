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

use Symfony\Component\Security\Http\PrivacyPass\Exception\InvalidPrivateTokenException;

/**
 * The token a client redeems with an origin, as the "token" authentication parameter carries it.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9577#section-2.2.1
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class PrivateToken
{
    private function __construct(
        public readonly TokenTypeInterface $tokenType,
        public readonly string $nonce,
        public readonly string $challengeDigest,
        public readonly string $tokenKeyId,
        public readonly string $authenticator,
    ) {
    }

    /**
     * @param string             $token     The value of the "token" authentication parameter
     * @param TokenTypeInterface $tokenType The issuance protocol the challenge the token answers asked for
     *
     * @throws InvalidPrivateTokenException If the value is not a base64url-encoded token of that type
     */
    public static function fromBase64Url(#[\SensitiveParameter] string $token, TokenTypeInterface $tokenType): self
    {
        $decoded = base64_decode(strtr($token, '-_', '+/'), true);

        if (false === $decoded) {
            throw new InvalidPrivateTokenException('The token is not base64url-encoded.');
        }

        return self::fromBinary($decoded, $tokenType);
    }

    /**
     * @throws InvalidPrivateTokenException If the bytes are not a token of the expected type
     */
    public static function fromBinary(#[\SensitiveParameter] string $token, TokenTypeInterface $tokenType): self
    {
        $keyIdLength = $tokenType->getKeyIdLength();

        if (\strlen($token) !== $length = 66 + $keyIdLength + $tokenType->getAuthenticatorLength()) {
            throw new InvalidPrivateTokenException(\sprintf('A token of type "0x%04X" is %d bytes long, %d given.', $tokenType->getValue(), $length, \strlen($token)));
        }
        if ($tokenType->getValue() !== $value = unpack('n', $token)[1]) {
            throw new InvalidPrivateTokenException(\sprintf('The token is of type "0x%04X", a token of type "0x%04X" was asked for.', $value, $tokenType->getValue()));
        }

        return new self($tokenType, substr($token, 2, 32), substr($token, 34, 32), substr($token, 66, $keyIdLength), substr($token, 66 + $keyIdLength));
    }

    /**
     * The value the authenticator of the token is computed over (RFC 9577, Section 2.2.3).
     */
    public function getAuthenticatorInput(): string
    {
        return pack('n', $this->tokenType->getValue()).$this->nonce.$this->challengeDigest.$this->tokenKeyId;
    }
}
