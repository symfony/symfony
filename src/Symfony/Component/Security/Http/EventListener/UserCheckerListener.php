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
use Symfony\Component\Ldap\Security\LdapBadge;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PreAuthenticatedUserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @final
 */
class UserCheckerListener implements EventSubscriberInterface
{
    /**
     * @param PasswordHasherFactoryInterface|null $hasherFactory     Hashes the presented password when the user checker rejects the user,
     *                                                               so that such users take as long to reject as the others
     * @param bool                                $hashLdapPasswords Whether to also hash the password of LDAP logins, as needed when some users are not checked by the LDAP server
     */
    public function __construct(
        private UserCheckerInterface $userChecker,
        private ?PasswordHasherFactoryInterface $hasherFactory = null,
        private bool $hashLdapPasswords = false,
    ) {
    }

    public function preCheckCredentials(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();
        if ($passport->hasBadge(PreAuthenticatedUserBadge::class)) {
            return;
        }

        $user = $passport->getUser();

        try {
            $this->userChecker->checkPreAuth($user);
        } catch (AuthenticationException $e) {
            $this->hashPassword($passport, $user);

            throw $e;
        }
    }

    public function postCheckCredentials(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();
        if (!$user instanceof UserInterface) {
            return;
        }

        $this->userChecker->checkPostAuth($user, $event->getAuthenticationToken());
    }

    private function hashPassword(Passport $passport, UserInterface $user): void
    {
        if (!$this->hasherFactory || !$user instanceof PasswordAuthenticatedUserInterface || (!$this->hashLdapPasswords && $passport->hasBadge(LdapBadge::class)) || !$passport->hasBadge(PasswordCredentials::class)) {
            return;
        }

        /** @var PasswordCredentials $badge */
        $badge = $passport->getBadge(PasswordCredentials::class);
        if ($badge->isResolved() || '' === $password = $badge->getPassword()) {
            return;
        }

        try {
            $this->hasherFactory->getPasswordHasher($user)->hash($password);
        } catch (\RuntimeException) {
            // no hasher is configured for the user, or the password is too long to be hashed
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckPassportEvent::class => ['preCheckCredentials', 256],
            AuthenticationSuccessEvent::class => ['postCheckCredentials', 256],
        ];
    }
}
