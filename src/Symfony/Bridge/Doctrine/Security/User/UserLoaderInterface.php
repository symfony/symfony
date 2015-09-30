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
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;

/**
 * Represents a class that loads UserInterface objects from Doctrine source for the authentication system.
 *
 * This interface is meant to facilitate the loading of a User from Doctrine source using a custom method.
 * If you want to implement your own logic of retrieving the user from Doctrine your repository should implement this
 * interface.
 *
 * @see UserInterface
 *
 * @author Michal Trojanowski <michal@kmt-studio.pl>
 */
interface UserLoaderInterface
{
    /**
     * Loads the user for the given username.
     *
     * This method must throw UsernameNotFoundException if the user is not
     * found.
     *
     * @param string $username The username
     *
     * @return UserInterface
     *
     * @see UsernameNotFoundException
     *
     * @throws UsernameNotFoundException if the user is not found
     */
    public function loadUserByUsername($username);
}
