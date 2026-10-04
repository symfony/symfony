<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\EventListener;

use Symfony\Component\Security\Http\Event\CheckRefreshedUserEvent;
use Symfony\Component\Security\Http\Exception\OidcSessionEndedException;
use Symfony\Component\Security\Http\Oidc\OidcEndedSessions;

/**
 * Deauthenticates a token whose OIDC provider session a back-channel logout ended, so that
 * the login stops working on the next request the browser makes.
 *
 * The logout token arrives outside of any request of the end-user, so its arrival can only
 * be written down; this is where it is acted on, the token being the one thing saying which
 * provider session the login belongs to.
 *
 * It is registered on the global event dispatcher rather than on the one of its firewall, so
 * that it runs on every firewall of the application: two firewalls sharing a context share the
 * token the session holds, and a login the provider ended has to be refused on a request to
 * either of them rather than on the one it was made on. `CheckRefreshedUserEvent` is one of the
 * events a global listener is copied onto every firewall dispatcher for.
 *
 * Running on every firewall does not make it refuse the logins of another: a token names the
 * firewall it was minted on, and only the listener of that firewall acts on it. Two providers
 * minting the same opaque "sid" for two firewalls would otherwise have one end the logins of
 * the other.
 *
 * It costs one cache lookup on each request carrying an "oidc_sid" attribute, and nothing on
 * the others.
 *
 * Deauthenticating is where it stops, which is what a password change does too: an application
 * wanting its session invalidated as well reads {@see OidcSessionEndedException} off the
 * deauthentication, {@see \Symfony\Component\Security\Http\Event\TokenDeauthenticatedEvent::getException()}.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class OidcBackChannelLogoutListener
{
    /**
     * @param string $firewallName The firewall whose provider sessions $endedSessions records
     */
    public function __construct(
        private readonly OidcEndedSessions $endedSessions,
        private readonly string $firewallName,
    ) {
    }

    /**
     * Two kinds of token are left untouched, and cost no lookup.
     *
     * One minted on another firewall, whose ended sessions another listener records, and one
     * naming no provider session at all, which a login made through another authenticator or
     * before this firewall asked for the "sid" claim cannot be matched by.
     */
    public function __invoke(CheckRefreshedUserEvent $event): void
    {
        $token = $event->getToken();

        if (!method_exists($token, 'getFirewallName') || $this->firewallName !== $token->getFirewallName()) {
            return;
        }

        $sid = $token->hasAttribute('oidc_sid') ? $token->getAttribute('oidc_sid') : null;

        if (!\is_string($sid) || '' === $sid) {
            return;
        }

        if ($this->endedSessions->has($sid)) {
            $event->setUserChanged(true, new OidcSessionEndedException());
        }
    }
}
