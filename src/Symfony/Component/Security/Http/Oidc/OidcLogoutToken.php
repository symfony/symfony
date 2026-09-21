<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Oidc;

use Jose\Component\Checker\AudienceChecker;
use Jose\Component\Checker\ClaimCheckerManager;
use Jose\Component\Checker\ExpirationTimeChecker;
use Jose\Component\Checker\InvalidClaimException;
use Jose\Component\Checker\IssuedAtChecker;
use Jose\Component\Checker\IssuerChecker;
use Jose\Component\Checker\MissingMandatoryClaimException;
use Psr\Clock\ClockInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Validates the claims of a logout token, the security event token of RFC 8417 a provider
 * pushes to say that one of its sessions ended.
 *
 * The signature is not verified here: the token arrives unauthenticated, from anyone able to
 * reach the back-channel logout endpoint, so it is {@see \Symfony\Component\Security\Http\Authenticator\Oidc\OidcSignatureVerifier}
 * that makes it the provider's, and that check can never be skipped.
 *
 * @see https://openid.net/specs/openid-connect-backchannel-1_0.html#Validation
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class OidcLogoutToken
{
    /**
     * The one member the "events" claim must carry, OpenID Connect Back-Channel Logout 1.0,
     * Section 2.4: it is what tells a logout token from any other security event token.
     */
    public const EVENT = 'http://schemas.openid.net/event/backchannel-logout';

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
     * Validates logout token claims per OpenID Connect Back-Channel Logout 1.0, Section 2.6,
     * and returns the identifier of the provider session that ended.
     *
     * A token carrying a "sub" and no "sid" names an end-user rather than a session, which is
     * what "backchannel_logout_session_required" is registered with the provider to prevent:
     * this client holds sessions, so it is told which one ended, and a token that does not say
     * is refused rather than logging every session of that user out.
     *
     * @param array<string, mixed> $claims
     *
     * @return string The "sid" claim, the provider session whose end the token announces
     *
     * @throws AuthenticationException If any claim validation fails
     */
    public function validateClaims(array $claims, string $expectedIssuer, string $expectedAudience): string
    {
        if (!class_exists(ClaimCheckerManager::class)) {
            throw new \LogicException('You cannot validate OIDC logout tokens since the "web-token/jwt-library" package is not installed. Try running "composer require web-token/jwt-library".');
        }

        try {
            // "exp" is not mandatory: a security event states what happened and does not
            // expire (RFC 8417, Section 2), but a provider sending one is taken at its word
            (new ClaimCheckerManager([
                new IssuerChecker([$expectedIssuer]),
                new AudienceChecker($expectedAudience),
                new IssuedAtChecker(clock: $this->clock, allowedTimeDrift: $this->allowedTimeDrift),
                new ExpirationTimeChecker(clock: $this->clock, allowedTimeDrift: $this->allowedTimeDrift),
            ]))->check($claims, ['iss', 'aud', 'iat', 'jti', 'events']);
        } catch (InvalidClaimException|MissingMandatoryClaimException $e) {
            throw new AuthenticationException(\sprintf('Invalid logout token: "%s"', $e->getMessage()), previous: $e);
        }

        // Section 2.4: a logout token carries no "nonce", and Section 2.6 has the client
        // check it, which is what keeps an ID token from being replayed as a logout token
        if (\array_key_exists('nonce', $claims)) {
            throw new AuthenticationException('Invalid logout token: it carries a "nonce" claim, which only an ID token does.');
        }

        $events = $claims['events'] ?? null;
        if (!\is_array($events) || !\array_key_exists(self::EVENT, $events) || !\is_array($events[self::EVENT])) {
            throw new AuthenticationException(\sprintf('Invalid logout token: the "events" claim must be an object carrying the "%s" member.', self::EVENT));
        }

        $sid = $claims['sid'] ?? null;
        if (!\is_string($sid) || '' === $sid) {
            throw new AuthenticationException('Invalid logout token: the "sid" claim must be a non-empty string naming the session that ended.');
        }

        return $sid;
    }
}
