<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Exception;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * The reason a login is deauthenticated when a back-channel logout ended its OIDC provider session.
 *
 * The session is not invalidated, as it can hold the logins of other firewalls. An application
 * that wants it invalidated, as a front-channel logout does by default, does it from the event:
 *
 *     #[AsEventListener]
 *     public function onDeauthenticated(TokenDeauthenticatedEvent $event): void
 *     {
 *         if ($event->getException() instanceof OidcSessionEndedException) {
 *             $event->getRequest()->getSession()->invalidate();
 *         }
 *     }
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
class OidcSessionEndedException extends AuthenticationException
{
    public function getMessageKey(): string
    {
        return 'The OIDC provider ended the session this login belongs to.';
    }
}
