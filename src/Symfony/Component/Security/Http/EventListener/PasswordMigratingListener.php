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
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\User\LegacyPasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PasswordUpgradeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @final
 */
class PasswordMigratingListener implements EventSubscriberInterface
{
    public function __construct(
        private PasswordHasherFactoryInterface $hasherFactory,
    ) {
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $passport = $event->getPassport();
        if (!$passport->hasBadge(PasswordUpgradeBadge::class)) {
            return;
        }

        /** @var PasswordUpgradeBadge $badge */
        $badge = $passport->getBadge(PasswordUpgradeBadge::class);
        $plaintextPassword = $badge->getAndErasePlaintextPassword();

        if ('' === $plaintextPassword) {
            return;
        }

        $user = $passport->getUser();
        if (!$user instanceof PasswordAuthenticatedUserInterface || null === $user->getPassword()) {
            return;
        }

        $passwordHasher = $this->hasherFactory->getPasswordHasher($user);
        if (!$passwordHasher->needsRehash($user->getPassword())) {
            return;
        }

        $passwordUpgrader = $badge->getPasswordUpgrader();

        if (null === $passwordUpgrader) {
            if (!$passport->hasBadge(UserBadge::class)) {
                return;
            }

            /** @var UserBadge $userBadge */
            $userBadge = $passport->getBadge(UserBadge::class);
            $userLoader = $userBadge->getUserLoader();
            if (\is_array($userLoader) && $userLoader[0] instanceof PasswordUpgraderInterface) {
                $passwordUpgrader = $userLoader[0];
            } elseif (!$userLoader instanceof \Closure
                || !($passwordUpgrader = (new \ReflectionFunction($userLoader))->getClosureThis()) instanceof PasswordUpgraderInterface
            ) {
                return;
            }
        }

        $salt = null;
        if ($user instanceof LegacyPasswordAuthenticatedUserInterface) {
            $salt = $user->getSalt();
        }

        $passwordUpgrader->upgradePassword($user, $passwordHasher->hash($plaintextPassword, $salt));
    }

    public static function getSubscribedEvents(): array
    {
        return [LoginSuccessEvent::class => 'onLoginSuccess'];
    }
}
