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
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
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
 */
class PasswordMigratingListener implements EventSubscriberInterface
{
    private $hasherFactory;

    /**
     * @param PasswordHasherFactoryInterface $hasherFactory
     */
    public function __construct($hasherFactory)
    {
        if ($hasherFactory instanceof EncoderFactoryInterface) {
            trigger_deprecation('symfony/security-core', '5.3', 'Passing a "%s" instance to the "%s" constructor is deprecated, use "%s" instead.', EncoderFactoryInterface::class, __CLASS__, PasswordHasherFactoryInterface::class);
        }

        $this->hasherFactory = $hasherFactory;
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
        if (null === $user->getPassword()) {
            return;
        }

        $passwordHasher = $this->hasherFactory instanceof EncoderFactoryInterface ? $this->hasherFactory->getEncoder($user) : $this->hasherFactory->getPasswordHasher($user);
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
            } else {
                return;
            }
        }

        $passwordUpgrader->upgradePassword($user, $passwordHasher instanceof PasswordHasherInterface ? $passwordHasher->hash($plaintextPassword, $user->getSalt()) : $passwordHasher->encodePassword($plaintextPassword, $user->getSalt()));
    }

    public static function getSubscribedEvents(): array
    {
        return [LoginSuccessEvent::class => 'onLoginSuccess'];
    }
}
