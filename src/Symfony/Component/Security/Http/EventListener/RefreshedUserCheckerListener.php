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

use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Http\Event\CheckRefreshedUserEvent;

/**
 * Checks the account status of a user reloaded from its user provider, so that
 * an account disabled, locked or expired during a session is rejected on the
 * next request instead of staying usable until the user logs out.
 *
 * Any AuthenticationException the checker throws deauthenticates the token, the
 * way the firewall treats them all alike during authentication.
 *
 * It runs on every request of a stateful firewall, so the checker it is given
 * must be safe to call that often; a chain collecting checkers registered by
 * packages the application does not control may not be.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class RefreshedUserCheckerListener
{
    public function __construct(
        private UserCheckerInterface $userChecker,
    ) {
    }

    public function __invoke(CheckRefreshedUserEvent $event): void
    {
        $token = $event->getToken();

        try {
            // when impersonating, mirror SwitchUserListener, which only runs the post-auth checks
            if (!$token instanceof SwitchUserToken) {
                $this->userChecker->checkPreAuth($event->getRefreshedUser());
            }

            $this->userChecker->checkPostAuth($event->getRefreshedUser(), $token);
        } catch (AuthenticationException $e) {
            $event->setUserChanged(true, $e);
        }
    }
}
