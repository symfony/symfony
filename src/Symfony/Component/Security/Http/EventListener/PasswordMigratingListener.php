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
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PasswordUpgradeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\UserPassportInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @final
 * @experimental in 5.2
 */
class PasswordMigratingListener implements EventSubscriberInterface
{
    private $encoderFactory;

    public function __construct(EncoderFactoryInterface $encoderFactory)
    {
        $this->encoderFactory = $encoderFactory;
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $passport = $event->getPassport();
        if (!$passport instanceof UserPassportInterface || !$passport->hasBadge(PasswordUpgradeBadge::class)) {
            return;
        }

        /** @var PasswordUpgradeBadge $badge */
        $badge = $passport->getBadge(PasswordUpgradeBadge::class);
        $plaintextPassword = $badge->getAndErasePlaintextPassword();

        if ('' === $plaintextPassword) {
            return;
        }

        $user = $passport->getUser();
        $passwordEncoder = $this->encoderFactory->getEncoder($user);
        if (!$passwordEncoder->needsRehash($user->getPassword())) {
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
            } else {
                return;
            }
        }

        $passwordUpgrader->upgradePassword($user, $passwordEncoder->encodePassword($plaintextPassword, $user->getSalt()));
    }

    public static function getSubscribedEvents(): array
    {
        return [LoginSuccessEvent::class => 'onLoginSuccess'];
    }
}
