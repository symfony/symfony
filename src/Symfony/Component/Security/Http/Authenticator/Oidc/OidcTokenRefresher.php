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

use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Exception\OidcInvalidGrantException;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;

/**
 * Renews the OIDC tokens a security token carries, with the refresh token grant
 * of RFC 6749, Section 6.
 *
 * The tokens the "oidc_login" authenticator obtained are held as attributes of the
 * security token, which the firewall keeps in the session: renewing them in place is
 * what keeps the access token usable to call an API on behalf of the logged-in user,
 * long after the short lifetime the provider gave it.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6749#section-6 Refreshing an access token
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class OidcTokenRefresher
{
    private readonly ClockInterface $clock;

    /**
     * @param string                     $clientId          The client the tokens were issued to, checked as the audience of a refreshed ID token
     * @param OidcSignatureVerifier|null $signatureVerifier Verifies the signature of a refreshed ID token against the
     *                                                      provider JWKS, or null to decode it without verifying it
     * @param int                        $leeway            How many seconds before its expiry the access token is renewed,
     *                                                      so that one handed to a downstream call does not expire in flight
     * @param ClockInterface|null        $clock             The clock the expiry is read and written against, or null to use
     *                                                      the clock of the "symfony/clock" component
     */
    public function __construct(
        private readonly OidcClientInterface $client,
        private readonly OidcDiscovery $discovery,
        private readonly OidcIdToken $idToken,
        private readonly string $clientId,
        private readonly ?OidcSignatureVerifier $signatureVerifier = null,
        private readonly int $leeway = 30,
        ?ClockInterface $clock = null,
    ) {
        if (null === $clock && !class_exists(Clock::class)) {
            throw new \LogicException(\sprintf('The "symfony/clock" component is required to build "%s" without a clock. Try running "composer require symfony/clock", or pass any PSR-20 clock to the constructor.', self::class));
        }

        $this->clock = $clock ?? new Clock();
    }

    /**
     * Renews the access token when it is about to expire, and reports whether it did.
     *
     * Nothing happens when the security token carries no refresh token, or when the
     * token endpoint reported no "expires_in", as nothing then says the access token
     * went stale.
     *
     * @throws OidcInvalidGrantException If the provider no longer honors the refresh token
     * @throws AuthenticationException   If the renewal fails for any other reason
     */
    public function refreshIfNeeded(TokenInterface $token): bool
    {
        $refreshToken = $token->hasAttribute('oidc_refresh_token') ? $token->getAttribute('oidc_refresh_token') : null;
        $expiresAt = $token->hasAttribute('oidc_access_token_expires_at') ? $token->getAttribute('oidc_access_token_expires_at') : null;

        if (!\is_string($refreshToken) || '' === $refreshToken || !is_numeric($expiresAt)) {
            return false;
        }

        if ($this->clock->now()->getTimestamp() + $this->leeway < (int) $expiresAt) {
            return false;
        }

        $this->refresh($token);

        return true;
    }

    /**
     * Renews the access token, whatever its expiry, and stores the new tokens on the
     * security token: the access token and its expiry, the refresh token when the
     * provider rotated it, and the ID token when it issued a new one.
     *
     * @throws OidcInvalidGrantException If the provider no longer honors the refresh token
     * @throws AuthenticationException   If the renewal fails for any other reason
     */
    public function refresh(TokenInterface $token): void
    {
        $refreshToken = $token->hasAttribute('oidc_refresh_token') ? $token->getAttribute('oidc_refresh_token') : null;
        if (!\is_string($refreshToken) || '' === $refreshToken) {
            throw new AuthenticationException('The security token carries no OIDC refresh token: the access token cannot be renewed.');
        }

        $tokenData = $this->client->refreshToken($refreshToken);

        if (!\is_string($tokenData['access_token'] ?? null) || '' === $tokenData['access_token']) {
            throw new AuthenticationException('The token endpoint response does not contain a valid "access_token".');
        }

        // the whole response is validated before anything is stored, so that a rejected
        // ID token leaves the security token exactly as it was
        if (isset($tokenData['id_token'])) {
            $this->verifyRefreshedIdToken($token, $tokenData['id_token']);
            $token->setAttribute('oidc_id_token', $tokenData['id_token']);
        }

        $token->setAttribute('oidc_access_token', $tokenData['access_token']);
        $token->setAttribute('oidc_access_token_expires_at', is_numeric($tokenData['expires_in'] ?? null) ? $this->clock->now()->getTimestamp() + (int) $tokenData['expires_in'] : null);

        // RFC 6749, Section 6: issuing a new refresh token is a MAY, and the one just
        // used stays valid until the provider replaces it
        if (\is_string($tokenData['refresh_token'] ?? null) && '' !== $tokenData['refresh_token']) {
            $token->setAttribute('oidc_refresh_token', $tokenData['refresh_token']);
        }
    }

    /**
     * Validates an ID token returned by the refresh token grant, per OIDC Core 1.0,
     * Section 12.2.
     *
     * Neither a "nonce" nor a "max_age" is checked here: no authorization request was
     * made, and the "auth_time" claim still reports the original authentication.
     *
     * @throws AuthenticationException If the refreshed ID token does not describe the same authentication
     */
    private function verifyRefreshedIdToken(TokenInterface $token, mixed $idToken): void
    {
        if (!\is_string($idToken) || '' === $idToken) {
            throw new AuthenticationException('The token endpoint response does not contain a valid "id_token".');
        }

        $claims = null !== $this->signatureVerifier ? $this->signatureVerifier->verify($idToken) : $this->idToken->decode($idToken);

        $this->idToken->validateClaims($claims, $this->discovery->getConfiguration()['issuer'] ?? '', $this->clientId);

        $previousIdToken = $token->hasAttribute('oidc_id_token') ? $token->getAttribute('oidc_id_token') : null;
        if (!\is_string($previousIdToken) || '' === $previousIdToken) {
            throw new AuthenticationException('The security token carries no OIDC ID token to compare the refreshed one against.');
        }

        $previousSubject = $this->idToken->decode($previousIdToken)['sub'] ?? null;
        if (!\is_string($previousSubject) || !\is_string($claims['sub'] ?? null) || !hash_equals($previousSubject, $claims['sub'])) {
            throw new AuthenticationException('The "sub" claim of the refreshed ID token does not match the one of the ID token issued at authentication.');
        }
    }
}
