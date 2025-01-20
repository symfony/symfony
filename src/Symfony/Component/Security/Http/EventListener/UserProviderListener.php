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

use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

/**
 * Configures the user provider as user loader, if no user load
 * has been explicitly set.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @final
 */
class UserProviderListener
{
    public function __construct(
        private UserProviderInterface $userProvider,
    ) {
    }

    public function checkPassport(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();
        if (!$passport->hasBadge(UserBadge::class)) {
            return;
        }

        /** @var UserBadge $badge */
        $badge = $passport->getBadge(UserBadge::class);
        if (null !== $badge->getUserLoader()) {
            return;
        }

        $badge->setUserLoader($this->userProvider->loadUserByIdentifier(...));
    }
}
