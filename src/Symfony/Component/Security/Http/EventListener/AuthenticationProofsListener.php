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

use Psr\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationMethod;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\AuthenticationMethodBadge;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Records on the token which authentication methods the user proved and when, and
 * keeps that record across the re-authentications of a session.
 *
 * The record is what IS_AUTHENTICATED_RECENTLY is checked against, so that a
 * sensitive action can require the user to prove possession of their credentials
 * again rather than relying on a session that was opened long ago.
 *
 * An interactive login is recorded at the moment it succeeds, under the methods its
 * AuthenticationMethodBadge states, added to whatever the token already holds; or as
 * unspecified when the authenticator states none and nothing is recorded yet. An
 * authenticator that knows better, such as an OIDC client reading the "amr" and
 * "auth_time" claims, records the proofs itself in createToken(), and this listener
 * never overwrites them.
 *
 * A successful authentication replaces the token, so the proofs the previous one
 * carried would be lost with it: a second factor proven at login has to survive a
 * password re-check hours later for a policy to be able to require both. They are
 * carried over as long as the user is the same, the newer entry winning.
 *
 * @see AuthenticatedVoter::IS_AUTHENTICATED_RECENTLY
 */
final class AuthenticationProofsListener implements EventSubscriberInterface
{
    public function __construct(
        private ?ClockInterface $clock = null,
    ) {
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $token = $event->getAuthenticatedToken();

        if (!method_exists($token, 'setAuthenticationProofs')) {
            trigger_deprecation('symfony/security-http', '8.2', 'Not implementing "%s::setAuthenticationProofs()" is deprecated, the method will be added to "%s" in 9.0; no authentication proof is recorded until then.', get_debug_type($token), TokenInterface::class);

            return;
        }

        $authenticator = $event->getAuthenticator();

        // a remember-me login is interactive, but presenting a cookie proves nothing
        // about the credentials, and the entry would outlive the token when it is
        // carried over to the next one
        if ($authenticator instanceof InteractiveAuthenticatorInterface && $authenticator->isInteractive() && !$token instanceof RememberMeToken) {
            $badge = $event->getPassport()->getBadge(AuthenticationMethodBadge::class);
            $now = $this->clock?->now()->getTimestamp() ?? time();

            // a stated method is a new proof even on a token that already holds some, as
            // the second step of a 2FA flow authenticates the first step's token again;
            // an unstated one only fills the gap left by an authenticator that recorded nothing
            if ($badge instanceof AuthenticationMethodBadge) {
                $token->setAuthenticationProofs(array_fill_keys($badge->methods, $now) + $token->getAuthenticationProofs());
            } elseif (!$token->getAuthenticationProofs()) {
                $token->setAuthenticationProofs([AuthenticationMethod::UNSPECIFIED => $now]);
            }
        }

        $previousToken = $event->getPreviousToken();

        if (null === $previousToken
            || !method_exists($previousToken, 'getAuthenticationProofs')
            || $previousToken->getUserIdentifier() !== $token->getUserIdentifier()
            || !$previousProofs = $previousToken->getAuthenticationProofs()
        ) {
            return;
        }

        $token->setAuthenticationProofs($token->getAuthenticationProofs() + $previousProofs);
    }

    public static function getSubscribedEvents(): array
    {
        // high priority so that a listener stopping propagation cannot leave the token
        // without the record IS_AUTHENTICATED_RECENTLY is decided on
        return [LoginSuccessEvent::class => ['onLoginSuccess', 256]];
    }
}
