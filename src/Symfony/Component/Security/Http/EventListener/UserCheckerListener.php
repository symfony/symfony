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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PreAuthenticatedUserBadge;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @final
 */
class UserCheckerListener implements EventSubscriberInterface
{
    private UserCheckerInterface $userChecker;

    public function __construct(UserCheckerInterface $userChecker)
    {
        $this->userChecker = $userChecker;
    }

    public function preCheckCredentials(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();
        if ($passport->hasBadge(PreAuthenticatedUserBadge::class)) {
            return;
        }

        $this->userChecker->checkPreAuth($passport->getUser());
    }

    public function postCheckCredentials(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();
        if (!$user instanceof UserInterface) {
            return;
        }

        $this->userChecker->checkPostAuth($user);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckPassportEvent::class => ['preCheckCredentials', 256],
            AuthenticationSuccessEvent::class => ['postCheckCredentials', 256],
        ];
    }
}
