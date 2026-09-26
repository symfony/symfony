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

use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Event\CheckRefreshedUserEvent;
use Symfony\Component\Security\Http\Oidc\OidcEndedSessions;

/**
 * Deauthenticates a token whose OIDC provider session a back-channel logout ended, so that
 * the login stops working on the next request the browser makes.
 *
 * The logout token arrives outside of any request of the end-user, so its arrival can only
 * be written down; this is where it is acted on, the token being the one thing saying which
 * provider session the login belongs to.
 *
 * It runs on every request of a stateful firewall and costs one cache lookup on each request
 * carrying an "oidc_sid" attribute.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class OidcBackChannelLogoutListener
{
    public function __construct(
        private readonly OidcEndedSessions $endedSessions,
    ) {
    }

    public function __invoke(CheckRefreshedUserEvent $event): void
    {
        $token = $event->getToken();
        $sid = $token->hasAttribute('oidc_sid') ? $token->getAttribute('oidc_sid') : null;

        // a login made before this firewall asked for "sid", or through another
        // authenticator, names no provider session and cannot be matched
        if (!\is_string($sid) || '' === $sid) {
            return;
        }

        if ($this->endedSessions->has($sid)) {
            $event->setUserChanged(true, new AuthenticationException('The OIDC provider ended the session this login belongs to.'));
        }
    }
}
