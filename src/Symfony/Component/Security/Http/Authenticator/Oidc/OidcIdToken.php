<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Oidc;

use Jose\Component\Checker\AudienceChecker;
use Jose\Component\Checker\ClaimCheckerManager;
use Jose\Component\Checker\ExpirationTimeChecker;
use Jose\Component\Checker\InvalidClaimException;
use Jose\Component\Checker\IssuedAtChecker;
use Jose\Component\Checker\IssuerChecker;
use Jose\Component\Checker\MissingMandatoryClaimException;
use Jose\Component\Checker\NotBeforeChecker;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\Signature\Serializer\JWSSerializerManager;
use Psr\Clock\ClockInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Decodes and validates OIDC ID tokens using the web-token (JOSE) library,
 * reusing the same claim checkers as the OIDC access-token handlers.
 *
 * The signature is not verified here: it is the job of {@see OidcSignatureVerifier},
 * which the "oidc_login" authenticator uses by default, and which the ID token
 * received directly from the token endpoint over TLS MAY do without
 * (OIDC Core 1.0, Section 3.1.3.7, item 6).
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class OidcIdToken
{
    /**
     * @param ClockInterface $clock The caller provides the clock (any PSR-20 implementation):
     *                              symfony/clock is not a dependency of this component, so no
     *                              default can be instantiated here
     */
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly int $allowedTimeDrift = 0,
    ) {
    }

    /**
     * Decodes the claims of a JWT without verifying its signature.
     *
     * @return array<string, mixed> The decoded claims
     *
     * @throws AuthenticationException If the token cannot be decoded
     */
    public function decode(string $jwt): array
    {
        if (!class_exists(JWSSerializerManager::class)) {
            throw new \LogicException('You cannot decode OIDC ID tokens since the "web-token/jwt-library" package is not installed. Try running "composer require web-token/jwt-library".');
        }

        try {
            $payload = (new JWSSerializerManager([new CompactSerializer()]))->unserialize($jwt)->getPayload();
        } catch (\InvalidArgumentException) {
            throw new AuthenticationException('Invalid ID token format.');
        }

        try {
            $claims = json_decode($payload ?? '', true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new AuthenticationException('Invalid ID token payload.');
        }

        if (!\is_array($claims)) {
            throw new AuthenticationException('Invalid ID token payload.');
        }

        return $claims;
    }

    /**
     * Validates ID token claims per OIDC Core 1.0, Section 3.1.3.7.
     *
     * @throws AuthenticationException If any claim validation fails
     */
    public function validateClaims(array $claims, string $expectedIssuer, string $expectedAudience, ?string $expectedNonce = null, ?int $maxAge = null): void
    {
        if (!class_exists(ClaimCheckerManager::class)) {
            throw new \LogicException('You cannot validate OIDC ID tokens since the "web-token/jwt-library" package is not installed. Try running "composer require web-token/jwt-library".');
        }

        try {
            (new ClaimCheckerManager([
                new IssuerChecker([$expectedIssuer]),
                new AudienceChecker($expectedAudience),
                new IssuedAtChecker(clock: $this->clock, allowedTimeDrift: $this->allowedTimeDrift),
                new NotBeforeChecker(clock: $this->clock, allowedTimeDrift: $this->allowedTimeDrift),
                new ExpirationTimeChecker(clock: $this->clock, allowedTimeDrift: $this->allowedTimeDrift),
            ]))->check($claims, ['iss', 'aud', 'exp', 'iat']);
        } catch (InvalidClaimException|MissingMandatoryClaimException $e) {
            throw new AuthenticationException(\sprintf('Invalid ID token: "%s"', $e->getMessage()), previous: $e);
        }

        // "azp" is only checked when present: OIDC Core 1.0, Section 3.1.3.7 items 3 to 5
        // are SHOULD, and the AudienceChecker above already ensures the client_id is one
        // of the audiences.
        if (isset($claims['azp']) && !hash_equals($expectedAudience, (string) $claims['azp'])) {
            throw new AuthenticationException('ID token "azp" claim does not match the expected client_id.');
        }

        if (null !== $expectedNonce && (!isset($claims['nonce']) || !hash_equals($expectedNonce, (string) $claims['nonce']))) {
            throw new AuthenticationException('ID token nonce does not match.');
        }

        if (null === $maxAge) {
            return;
        }

        if (!isset($claims['auth_time']) || !is_numeric($claims['auth_time'])) {
            throw new AuthenticationException('ID token is missing the "auth_time" claim required when "max_age" is requested.');
        }

        if ($this->clock->now()->getTimestamp() - (int) $claims['auth_time'] > $maxAge + $this->allowedTimeDrift) {
            throw new AuthenticationException('ID token "auth_time" exceeds the requested "max_age"; re-authentication is required.');
        }
    }
}
