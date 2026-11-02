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
 * The challenge an origin sends to ask a client for a Privacy Pass token.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9577#section-2.1.1
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class TokenChallenge
{
    /**
     * @param string             $issuerName        The host, with an optional port, of the issuer allowed to issue the tokens this challenge accepts
     * @param TokenTypeInterface $tokenType         The issuance protocol the token has to come from
     * @param string             $redemptionContext 32 bytes binding the tokens to a context of the origin's choosing, or an empty string to accept context-less tokens
     * @param list<string>       $originInfo        The hosts the tokens may be redeemed with, this origin included, or an empty list to accept cross-origin tokens
     */
    public function __construct(
        public readonly string $issuerName,
        public readonly TokenTypeInterface $tokenType,
        public readonly string $redemptionContext = '',
        public readonly array $originInfo = [],
    ) {
        $value = $tokenType->getValue();

        if (0 > $value || 0xFFFF < $value) {
            throw new \InvalidArgumentException(\sprintf('The token type "%s" must declare a 16-bit value, "%d" given.', get_debug_type($tokenType), $value));
        }
        if ('' === $issuerName || \strlen($issuerName) > 0xFFFF) {
            throw new \InvalidArgumentException('The issuer name must be between 1 and 65535 bytes long.');
        }
        if (!self::isServerName($issuerName)) {
            throw new \InvalidArgumentException(\sprintf('The issuer name "%s" must be an ASCII host with an optional port, and no user information.', $issuerName));
        }
        if ('' !== $redemptionContext && 32 !== \strlen($redemptionContext)) {
            throw new \InvalidArgumentException(\sprintf('The redemption context must be either empty or 32 bytes long, %d bytes given.', \strlen($redemptionContext)));
        }
        foreach ($originInfo as $origin) {
            if (!self::isServerName($origin)) {
                throw new \InvalidArgumentException(\sprintf('The origin name "%s" must be an ASCII host with an optional port, and no user information.', $origin));
            }
        }
        if (\strlen(implode(',', $originInfo)) > 0xFFFF) {
            throw new \InvalidArgumentException('The origin information must be at most 65535 bytes long.');
        }
    }

    /**
     * The value of the "challenge" authentication parameter, base64url-encoded with its padding (RFC 9577, Section 2.1.2).
     */
    public function toBase64Url(): string
    {
        return strtr(base64_encode($this->toBinary()), '+/', '-_');
    }

    /**
     * The digest a token redeeming this challenge carries (RFC 9577, Section 2.2.1).
     */
    public function getDigest(): string
    {
        return hash('sha256', $this->toBinary(), true);
    }

    /**
     * Whether a name is an ASCII host with an optional port, as RFC 9577, Section 2.1.1.1, requires of the names a challenge carries.
     *
     * @internal
     */
    public static function isServerName(string $name): bool
    {
        if (!preg_match('/^[\x21-\x7E]++$/', $name)) {
            return false;
        }

        $parts = parse_url('//'.$name);

        return false !== $parts && isset($parts['host']) && [] === array_diff_key($parts, ['host' => 0, 'port' => 0]);
    }

    private function toBinary(): string
    {
        $originInfo = implode(',', $this->originInfo);

        return pack('nn', $this->tokenType->getValue(), \strlen($this->issuerName))
            .$this->issuerName
            .\chr(\strlen($this->redemptionContext)).$this->redemptionContext
            .pack('n', \strlen($originInfo)).$originInfo;
    }
}
