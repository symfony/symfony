<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Security\User;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Represents a class that loads UserInterface objects from Doctrine source for the authentication system.
 *
 * This interface is meant to facilitate the loading of a User from Doctrine source using a custom method.
 * If you want to implement your own logic of retrieving the user from Doctrine your repository should implement this
 * interface.
 *
 * @see UserInterface
 *
 * @method UserInterface|null loadUserByIdentifier(string $identifier) loads the user for the given user identifier (e.g. username or email).
 *                                                                     This method must return null if the user is not found.
 *
 * @author Michal Trojanowski <michal@kmt-studio.pl>
 */
interface UserLoaderInterface
{
    /**
     * @return UserInterface|null
     *
     * @deprecated since Symfony 5.3, use loadUserByIdentifier() instead
     */
    public function loadUserByUsername(string $username);
}
