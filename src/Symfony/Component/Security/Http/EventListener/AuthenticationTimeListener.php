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
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

/**
 * Records on the token when the user last authenticated interactively.
 *
 * The recorded time is what IS_AUTHENTICATED_RECENTLY is checked against, so that
 * a sensitive action can require the user to prove possession of their credentials
 * again rather than relying on a session that was opened long ago.
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
        $event->getAuthenticationToken()->setAttribute(AuthenticatedVoter::AUTH_TIME_ATTRIBUTE, $this->clock?->now()->getTimestamp() ?? time());
    }

    public static function getSubscribedEvents(): array
    {
        // high priority so that a listener stopping propagation cannot leave the token
        // without the stamp IS_AUTHENTICATED_RECENTLY is decided on
        return [SecurityEvents::INTERACTIVE_LOGIN => ['onInteractiveLogin', 256]];
    }
}
