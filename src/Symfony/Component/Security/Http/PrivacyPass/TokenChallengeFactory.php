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

use Symfony\Component\HttpFoundation\Request;

/**
 * Builds the challenges an origin sends, and accepts a token for.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9577#section-2.1
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class TokenChallengeFactory
{
    /**
     * The token types reserved for greasing.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9577#section-6.2.1
     */
    private const RESERVED_TYPES = [0x0000, 0x02AA, 0x1132, 0x2E96, 0x3CD3, 0x4473, 0x5A63, 0x6D32, 0x7F3F, 0x8D07, 0x916B, 0xA6A4, 0xBEAB, 0xC3F3, 0xDA42, 0xE944, 0xF057];

    /**
     * @param string                      $issuerName        The host, with an optional port, of the issuer the origin accepts the tokens of
     * @param iterable<TokenKeyInterface> $tokenKeys         The public keys of that issuer, the one to challenge with first, the others still accepted while they rotate
     * @param RedemptionContextInterface  $redemptionContext What the tokens are bound to
     * @param list<string>|null           $originInfo        The hosts the tokens may be redeemed with, an empty list for cross-origin tokens, or null to bind them to this origin alone
     * @param int|null                    $maxAge            How long the challenge is advertised as accepted, in seconds, or null to read it from the redemption context
     * @param int                         $greasePercentage  The share of the challenges that carry a second, meaningless challenge of a reserved token type, so that clients keep ignoring the types they do not know
     */
    public function __construct(
        private readonly string $issuerName,
        private readonly TokenTypeInterface $tokenType,
        private readonly iterable $tokenKeys,
        private readonly RedemptionContextInterface $redemptionContext,
        private readonly ?array $originInfo = null,
        private readonly ?int $maxAge = null,
        private readonly int $greasePercentage = 0,
    ) {
        if (null !== $maxAge && null !== $lifetime = $redemptionContext->getLifetime()) {
            if (0 > $maxAge) {
                throw new \InvalidArgumentException(\sprintf('The max-age of a challenge cannot be negative, "%d" given.', $maxAge));
            }
            if ($maxAge > $lifetime) {
                throw new \InvalidArgumentException(\sprintf('A challenge cannot be advertised as accepted for %d seconds: its redemption context stops accepting it after %d.', $maxAge, $lifetime));
            }
        }
        if (0 > $greasePercentage || 100 < $greasePercentage) {
            throw new \InvalidArgumentException(\sprintf('The share of greased challenges must be a percentage, "%d" given.', $greasePercentage));
        }
    }

    public function getTokenType(): TokenTypeInterface
    {
        return $this->tokenType;
    }

    /**
     * @return iterable<TokenKeyInterface>
     */
    public function getTokenKeys(): iterable
    {
        return $this->tokenKeys;
    }

    /**
     * The challenges a token of this request is accepted for, the one the client was challenged with first.
     *
     * @return list<TokenChallenge>
     */
    public function getChallenges(Request $request): array
    {
        $originInfo = $this->getOriginInfo($request);
        $challenges = [];

        foreach ($this->redemptionContext->getAcceptedContexts($request) as $context) {
            $challenges[] = new TokenChallenge($this->issuerName, $this->tokenType, $context, $originInfo);
        }

        return $challenges;
    }

    /**
     * The value of the "WWW-Authenticate" header asking the client for a token, the challenge this origin prefers first.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9577#section-2.1.2
     *
     * @throws \LogicException If the origin has no key to challenge with
     */
    public function getChallengeHeader(Request $request): string
    {
        foreach ($this->tokenKeys as $tokenKey) {
            $challenge = $this->getChallenges($request)[0]->toBase64Url();
            $key = $tokenKey->toBase64Url();
            $maxAge = $this->maxAge ?? $this->redemptionContext->getLifetime();
            $challenges = [self::format($challenge, $key, $maxAge)];

            if ($this->greasePercentage > random_int(0, 99)) {
                $challenges[] = self::format(
                    self::grease(\strlen(base64_decode(strtr($challenge, '-_', '+/'), true))),
                    self::randomBase64Url(\strlen(base64_decode(strtr($key, '-_', '+/'), true))),
                    $maxAge,
                );
            }

            return implode(', ', $challenges);
        }

        throw new \LogicException(\sprintf('The origin has no token key of the issuer "%s" to challenge with.', $this->issuerName));
    }

    private static function format(string $challenge, string $tokenKey, ?int $maxAge): string
    {
        $values = [\sprintf('challenge="%s"', $challenge), \sprintf('token-key="%s"', $tokenKey)];

        if (null !== $maxAge) {
            $values[] = \sprintf('max-age="%d"', $maxAge);
        }

        return 'PrivateToken '.implode(', ', $values);
    }

    /**
     * A challenge of a reserved token type, which a client ignores, filled with random bytes and sized like the real one so that it does not stand out.
     */
    private static function grease(int $length): string
    {
        $type = self::RESERVED_TYPES[random_int(0, \count(self::RESERVED_TYPES) - 1)];

        return strtr(base64_encode(pack('n', $type).random_bytes(max(0, $length - 2))), '+/', '-_');
    }

    private static function randomBase64Url(int $length): string
    {
        return strtr(base64_encode(random_bytes($length)), '+/', '-_');
    }

    /**
     * @return list<string>
     *
     * @throws \LogicException If the origin is not one of the names the tokens are scoped to
     */
    private function getOriginInfo(Request $request): array
    {
        if (null === $this->originInfo) {
            return [$request->getHttpHost()];
        }
        if ([] !== $this->originInfo && !\in_array(strtolower($request->getHttpHost()), array_map('strtolower', $this->originInfo), true)) {
            throw new \LogicException(\sprintf('The origin "%s" must be one of the names the tokens are scoped to, "%s" given.', $request->getHttpHost(), implode('", "', $this->originInfo)));
        }

        return $this->originInfo;
    }
}
