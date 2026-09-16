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
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\SecurityEvents;

/**
 * Records on the token when the user last authenticated interactively, and keeps
 * that record across the re-authentications of a session.
 *
 * The record is what IS_AUTHENTICATED_RECENTLY is checked against, so that a
 * sensitive action can require the user to prove possession of their credentials
 * again rather than relying on a session that was opened long ago.
 *
 * The method that was proven is left unspecified here, because the event says only
 * that an interactive authentication happened. An authenticator that knows better,
 * such as an OIDC client reading the "amr" and "auth_time" claims, records it itself
 * in createToken(); this listener only fills the gap, it never overwrites it.
 *
 * A successful authentication replaces the token, so the proofs the previous one
 * carried would be lost with it: a second factor proven at login has to survive a
 * password re-check hours later for a policy to be able to require both. They are
 * carried over as long as the user is the same, the newer entry winning.
 *
 * @see AuthenticatedVoter::IS_AUTHENTICATED_RECENTLY
 */
final class AuthenticationTimeListener implements EventSubscriberInterface
{
    public function __construct(
        private ?ClockInterface $clock = null,
    ) {
    }

    public function onInteractiveLogin(InteractiveLoginEvent $event): void
    {
        $token = $event->getAuthenticationToken();

        if (!method_exists($token, 'setAuthenticationProofs')) {
            trigger_deprecation('symfony/security-http', '8.2', 'Not implementing "%s::setAuthenticationProofs()" is deprecated, the method will be added to "%s" in 9.0; no authentication proof is recorded until then.', get_debug_type($token), TokenInterface::class);

            return;
        }

        if (!$token->getAuthenticationProofs()) {
            $token->setAuthenticationProofs([AuthenticationMethod::UNSPECIFIED => $this->clock?->now()->getTimestamp() ?? time()]);
        }
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $previousToken = $event->getPreviousToken();
        $token = $event->getAuthenticatedToken();

        if (null === $previousToken
            || !method_exists($previousToken, 'getAuthenticationProofs')
            || !method_exists($token, 'setAuthenticationProofs')
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
        return [
            SecurityEvents::INTERACTIVE_LOGIN => ['onInteractiveLogin', 256],
            LoginSuccessEvent::class => ['onLoginSuccess', 256],
        ];
    }
}
