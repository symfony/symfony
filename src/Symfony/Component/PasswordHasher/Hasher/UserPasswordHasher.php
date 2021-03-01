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

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Hashes passwords based on the user and the PasswordHasherFactory.
 *
 * @author Ariel Ferrandini <arielferrandini@gmail.com>
 */
class UserPasswordHasher implements UserPasswordHasherInterface
{
    private $hasherFactory;

    public function __construct(PasswordHasherFactoryInterface $hasherFactory)
    {
        $this->hasherFactory = $hasherFactory;
    }

    public function hashPassword(UserInterface $user, string $plainPassword): string
    {
        $hasher = $this->hasherFactory->getPasswordHasher($user);

        return $hasher->hash($plainPassword, $user->getSalt());
    }

    /**
     * {@inheritdoc}
     */
    public function isPasswordValid(UserInterface $user, string $plainPassword): bool
    {
        if (null === $user->getPassword()) {
            return false;
        }

        $hasher = $this->hasherFactory->getPasswordHasher($user);

        return $hasher->verify($user->getPassword(), $plainPassword, $user->getSalt());
    }

    /**
     * {@inheritdoc}
     */
    public function needsRehash(UserInterface $user): bool
    {
        if (null === $user->getPassword()) {
            return false;
        }

        $hasher = $this->hasherFactory->getPasswordHasher($user);

        return $hasher->needsRehash($user->getPassword());
    }
}
