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

use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * PasswordHasherFactoryInterface to support different password hashers for different user accounts.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface PasswordHasherFactoryInterface
{
    /**
     * Returns the password hasher to use for the given user.
     *
     * @param PasswordAuthenticatedUserInterface|UserInterface|string $user A PasswordAuthenticatedUserInterface/UserInterface instance or a class name
     *
     * @throws \RuntimeException When no password hasher could be found for the user
     */
    public function getPasswordHasher($user): PasswordHasherInterface;
}
