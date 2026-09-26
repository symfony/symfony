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

use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcSignatureVerifier;

/**
 * Records the end of the session an OIDC provider says has ended, on the logout token it pushed.
 *
 * @see https://openid.net/specs/openid-connect-backchannel-1_0.html
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class OidcBackChannelLogout
{
    public function __construct(
        private readonly OidcSignatureVerifier $signatureVerifier,
        private readonly OidcLogoutToken $logoutToken,
        private readonly OidcDiscovery $discovery,
        private readonly string $clientId,
        private readonly OidcEndedSessions $endedSessions,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Verifies the given logout token and records that the session it names has ended.
     *
     * Nothing of the end-user is ended here: the request comes from the provider and carries
     * nothing of the browser that logged in, not even its session cookie. It is the token of
     * that browser that is refused on its next request, by
     * {@see \Symfony\Component\Security\Http\EventListener\OidcBackChannelLogoutListener}.
     *
     * A token naming a session this application never opened is not an error, and neither is
     * one naming a session that already ended: both are recorded and cost nothing, and a
     * provider telling a client about a session that client never opened looks like either.
     *
     * @throws AuthenticationException If the token is not one the provider issued, or does
     *                                 not say which session ended
     */
    public function logout(string $logoutToken): void
    {
        // nothing of the token is the provider's until its signature says so
        try {
            $claims = $this->signatureVerifier->verify($logoutToken);
        } catch (AuthenticationException $e) {
            $this->logger?->warning('The back-channel logout token could not be verified.', ['exception' => $e]);

            // the reason is logged rather than answered: the endpoint is reachable by
            // anyone, and the verifier names what it verifies after the ID token it is
            // built for, which a logout token is not
            throw new AuthenticationException('The logout token could not be verified.', previous: $e);
        }

        $sid = $this->logoutToken->validateClaims(
            $claims,
            $this->discovery->getConfiguration()['issuer'] ?? '',
            $this->clientId,
        );

        $this->endedSessions->record($sid);
    }
}
