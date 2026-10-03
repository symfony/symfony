<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\EventBundle\EventListener;

use Symfony\Component\Security\Http\Event\TokenDeauthenticatedEvent;
use Symfony\Component\Security\Http\Exception\OidcSessionEndedException;

/**
 * Invalidates the session when a back-channel logout ended the provider session of the login.
 */
final class OidcSessionEndedListener
{
    public function __invoke(TokenDeauthenticatedEvent $event): void
    {
        if ($event->getException() instanceof OidcSessionEndedException) {
            $event->getRequest()->getSession()->invalidate();
        }
    }
}
