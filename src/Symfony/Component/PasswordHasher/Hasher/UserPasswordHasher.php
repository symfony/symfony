<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PasswordHasher\Hasher;

use Symfony\Component\Security\Core\User\LegacyPasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * Hashes passwords based on the user and the PasswordHasherFactory.
 *
 * @author Ariel Ferrandini <arielferrandini@gmail.com>
 *
 * @final
 */
class UserPasswordHasher implements UserPasswordHasherInterface
{
    public function __construct(
        private PasswordHasherFactoryInterface $hasherFactory,
    ) {
    }

    public function hashPassword(PasswordAuthenticatedUserInterface $user, #[\SensitiveParameter] string $plainPassword): string
    {
        $salt = null;
        if ($user instanceof LegacyPasswordAuthenticatedUserInterface) {
            $salt = $user->getSalt();
        }

        $hasher = $this->hasherFactory->getPasswordHasher($user);

        return $hasher->hash($plainPassword, $salt);
    }

    public function isPasswordValid(PasswordAuthenticatedUserInterface $user, #[\SensitiveParameter] string $plainPassword): bool
    {
        $salt = null;
        if ($user instanceof LegacyPasswordAuthenticatedUserInterface) {
            $salt = $user->getSalt();
        }

        if (null === $user->getPassword()) {
            return false;
        }

        $hasher = $this->hasherFactory->getPasswordHasher($user);

        return $hasher->verify($user->getPassword(), $plainPassword, $salt);
    }

    public function needsRehash(PasswordAuthenticatedUserInterface $user): bool
    {
        if (null === $user->getPassword()) {
            return false;
        }

        $hasher = $this->hasherFactory->getPasswordHasher($user);

        return $hasher->needsRehash($user->getPassword());
    }
}
