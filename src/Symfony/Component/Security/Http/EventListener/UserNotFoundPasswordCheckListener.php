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
use Symfony\Component\PasswordHasher\Exception\InvalidPasswordException;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

/**
 * Hashes the presented password when the user is not found.
 *
 * This makes a login with an unknown user identifier take about as long as a login with a wrong password.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class UserNotFoundPasswordCheckListener implements EventSubscriberInterface
{
    private PasswordHasherInterface|false $hasher;

    /**
     * @param bool $hashLdapPasswords Whether to also hash the password of LDAP logins, as needed when some users are not checked by the LDAP server
     */
    public function __construct(
        private PasswordHasherFactoryInterface $hasherFactory,
        private string $userClass,
        private bool $hashLdapPasswords = false,
    ) {
    }

    public function checkPassport(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();
        if ((!$this->hashLdapPasswords && $passport->hasBadge(LdapBadge::class)) || !$passport->hasBadge(PasswordCredentials::class)) {
            return;
        }

        /** @var PasswordCredentials $badge */
        $badge = $passport->getBadge(PasswordCredentials::class);
        if ($badge->isResolved() || '' === $badge->getPassword()) {
            return;
        }

        try {
            $passport->getUser();
        } catch (UserNotFoundException $e) {
            $this->hashPassword($badge->getPassword());

            throw $e;
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [CheckPassportEvent::class => ['checkPassport', 257]];
    }

    private function hashPassword(#[\SensitiveParameter] string $password): void
    {
        if (!isset($this->hasher)) {
            try {
                $this->hasher = $this->hasherFactory->getPasswordHasher($this->userClass);
            } catch (\RuntimeException) {
                $this->hasher = false;
            }
        }

        if (!$this->hasher) {
            return;
        }

        try {
            $this->hasher->hash($password);
        } catch (InvalidPasswordException) {
            // verify() doesn't hash passwords that are too long either
        }
    }
}
